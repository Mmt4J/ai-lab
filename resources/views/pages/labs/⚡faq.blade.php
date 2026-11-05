<?php

use App\Ai\Agents\FaqAssistant;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    /**
     * A small FAQ the lab starts with. Users can replace it with their own.
     */
    public const SAMPLE_FAQ = <<<'FAQ'
    Q: How long does delivery take?
    A: Orders are dispatched within 1 business day. Standard delivery takes 3-5 working days, and express delivery takes 1-2 working days.

    Q: Do you ship internationally?
    A: Yes. We ship to over 40 countries. International delivery usually takes 7-14 working days and costs a flat 15 USD.

    Q: What is your returns policy?
    A: You can return unused items within 30 days of delivery for a full refund. Return shipping is free for orders within the country.

    Q: How can I track my order?
    A: We email a tracking link as soon as your order is dispatched. You can also track it under Orders in your account.

    Q: Which payment methods do you accept?
    A: We accept Visa, Mastercard, American Express, PayPal, and Apple Pay.

    Q: How do I contact support?
    A: Our support team is available Monday to Friday, 9am to 6pm, at support@example.com.
    FAQ;

    /**
     * The FAQ context pasted by the user. It is sent with every reply.
     */
    #[Validate('required|string|max:12000', message: 'Paste a FAQ of 12,000 characters or fewer before chatting.')]
    public string $faq = self::SAMPLE_FAQ;

    #[Validate('required|string|max:2000', message: 'Please enter a question of 2,000 characters or fewer.')]
    public string $question = '';

    /**
     * The full conversation, oldest first. Each turn has a role and content.
     *
     * @var list<array{role: string, content: string}>
     */
    public array $messages = [];

    /**
     * A user-facing message shown when the AI request fails.
     */
    public string $errorMessage = '';

    /**
     * Send the current question with the earlier turns as context.
     */
    public function send(): void
    {
        $this->validate();

        $question = trim($this->question);

        // The model is stateless: send every earlier turn so it can follow
        // up on things the customer already said. The current question is
        // passed separately as the prompt below.
        $history = $this->messages;

        try {
            $response = (new FaqAssistant($history, $this->faq))->prompt($question);
        } catch (RateLimitedException $e) {
            $this->errorMessage = 'The AI service is rate limited right now. Please try again in a moment.';

            return;
        } catch (InsufficientCreditsException $e) {
            $this->errorMessage = 'The AI service has insufficient credits. Please try again later.';

            return;
        } catch (ProviderOverloadedException $e) {
            $this->errorMessage = 'The AI service is temporarily overloaded. Please try again shortly.';

            return;
        } catch (ProviderConnectionException $e) {
            $this->errorMessage = 'The AI service could not be reached. Check your connection and try again.';

            return;
        } catch (Throwable $e) {
            // Log the exception class only so the FAQ and chat content never
            // end up in the application log.
            Log::error('FAQ assistant request failed.', ['exception' => $e::class]);

            $this->errorMessage = 'Something went wrong while contacting the AI service. Please try again.';

            return;
        }

        $answer = trim($response->text);

        if ($answer === '') {
            $this->errorMessage = 'The assistant returned an empty answer. Please try again.';

            return;
        }

        $this->messages[] = ['role' => 'user', 'content' => $question];
        $this->messages[] = ['role' => 'assistant', 'content' => $answer];
        $this->question = '';
        $this->errorMessage = '';
    }

    /**
     * Ask one of the starter questions.
     */
    public function ask(string $question): void
    {
        $this->question = $question;

        $this->send();
    }

    /**
     * Clear the conversation while keeping the FAQ knowledge base.
     */
    public function resetConversation(): void
    {
        $this->reset(['messages', 'question', 'errorMessage']);
        $this->resetValidation();
    }

    /**
     * Replace the FAQ with the sample content shipped with the lab.
     */
    public function restoreSampleFaq(): void
    {
        $this->faq = self::SAMPLE_FAQ;
        $this->errorMessage = '';
        $this->resetValidation('faq');
    }
};
?>

<div class="mx-auto w-full max-w-6xl space-y-6">
    <div class="space-y-1">
        <flux:heading size="xl">FAQ Chatbot</flux:heading>
        <flux:text class="text-sm text-muted">
            Ask questions about a small FAQ and the assistant will answer only from it. The whole conversation is sent
            back with every question, so follow-up questions keep their context.
        </flux:text>
    </div>

    @if ($errorMessage !== '')
        <div class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20"
            role="alert">
            <flux:icon.exclamation-triangle variant="micro" class="mt-0.5 size-5 shrink-0 text-red-500" />
            <p class="text-sm text-red-700 dark:text-red-300">{{ $errorMessage }}</p>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="flex flex-col gap-4 lg:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="space-y-1">
                    <flux:heading size="lg">Chat</flux:heading>
                    <flux:text class="text-sm text-muted">
                        The assistant remembers this conversation until you start a new one.
                    </flux:text>
                </div>

                <div class="flex items-center gap-2">
                    <flux:badge size="sm">{{ count($messages) }} messages</flux:badge>
                    <flux:button size="sm" variant="ghost" icon="arrow-path" wire:click="resetConversation"
                        wire:loading.attr="disabled" wire:target="send,ask" :disabled="count($messages) === 0">
                        New chat
                    </flux:button>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
                <div class="flex h-[28rem] flex-col">
                    <div id="faq-chat-messages" class="flex-1 space-y-4 overflow-y-auto p-4" role="log"
                        aria-live="polite" aria-label="Conversation with the FAQ assistant">
                        @if (count($messages) === 0)
                            <div class="flex h-full flex-col items-center justify-center gap-4 text-center">
                                <div class="rounded-full bg-zinc-100 p-3 dark:bg-zinc-800">
                                    <flux:icon.chat-bubble-left-right class="size-6 text-zinc-500 dark:text-zinc-400" />
                                </div>

                                <div class="space-y-1">
                                    <p class="font-medium">Ask about anything in your FAQ</p>
                                    <p class="text-sm text-muted">
                                        Try one of these questions, or type your own below.
                                    </p>
                                </div>

                                <div class="flex flex-wrap justify-center gap-2">
                                    @foreach (['How long does delivery take?', 'Do you ship internationally?', 'What is your returns policy?'] as $suggestion)
                                        <button type="button" wire:click="ask(@js($suggestion))"
                                            wire:loading.attr="disabled" wire:target="send,ask"
                                            class="rounded-full border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-700 transition-colors hover:bg-zinc-100 disabled:opacity-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800">
                                            {{ $suggestion }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            @foreach ($messages as $index => $message)
                                @if ($message['role'] === 'user')
                                    <div class="flex justify-end" wire:key="message-{{ $index }}">
                                        <div
                                            class="max-w-[85%] rounded-2xl rounded-br-sm bg-zinc-900 px-4 py-2.5 text-sm text-white dark:bg-zinc-100 dark:text-zinc-900">
                                            <span class="sr-only">You asked: </span>
                                            <p class="whitespace-pre-line">{{ $message['content'] }}</p>
                                        </div>
                                    </div>
                                @else
                                    <div class="flex items-start gap-3" wire:key="message-{{ $index }}">
                                        <div
                                            class="mt-0.5 shrink-0 rounded-full bg-zinc-100 p-2 dark:bg-zinc-800">
                                            <flux:icon.sparkles variant="micro"
                                                class="size-4 text-zinc-500 dark:text-zinc-400" />
                                        </div>

                                        <div
                                            class="max-w-[85%] rounded-2xl rounded-bl-sm bg-zinc-100 px-4 py-2.5 text-sm dark:bg-zinc-800">
                                            <span class="sr-only">Assistant answered: </span>
                                            <p class="whitespace-pre-line">{{ $message['content'] }}</p>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        @endif

                        <div wire:loading wire:target="send,ask" class="flex items-start gap-3" role="status"
                            aria-live="polite">
                            <div class="mt-0.5 shrink-0 rounded-full bg-zinc-100 p-2 dark:bg-zinc-800">
                                <flux:icon.sparkles variant="micro"
                                    class="size-4 animate-pulse text-zinc-500 dark:text-zinc-400" />
                            </div>
                            <div class="rounded-2xl rounded-bl-sm bg-zinc-100 px-4 py-2.5 text-sm text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                                Thinking&hellip;
                            </div>
                        </div>

                        {{-- Re-keying the anchor on every turn re-runs x-init, which keeps the newest message in view. --}}
                        <div wire:key="scroll-anchor-{{ count($messages) }}"
                            x-init="$el.parentElement.scrollTop = $el.parentElement.scrollHeight"></div>
                    </div>

                    <div class="border-t border-zinc-200 p-3 dark:border-zinc-700">
                        <form wire:submit="send" class="flex items-center gap-2">
                            <div class="min-w-0 flex-1">
                                <flux:input wire:model="question" placeholder="Ask a question about the FAQ..."
                                    autocomplete="off" maxlength="2000" aria-label="Your question" />
                            </div>

                            <flux:button type="submit" variant="primary" icon-trailing="paper-airplane"
                                wire:loading.attr="disabled" wire:target="send,ask">
                                <span wire:loading.remove wire:target="send,ask">Send</span>
                                <span wire:loading wire:target="send,ask">Sending</span>
                            </flux:button>
                        </form>
                    </div>
                </div>
            </div>

            <flux:text class="text-xs text-muted">
                The assistant only knows what is in the FAQ. If the answer is not there, it will say so instead of
                guessing.
            </flux:text>
        </flux:card>

        <flux:card class="space-y-4">
            <div class="space-y-1">
                <flux:heading size="lg">FAQ knowledge base</flux:heading>
                <flux:text class="text-sm text-muted">
                    Paste your own questions and answers here. The whole list is sent as context with every reply.
                </flux:text>
            </div>

            <flux:textarea wire:model="faq" rows="16" maxlength="12000" aria-label="FAQ knowledge base"
                placeholder="Q: How long does delivery take?&#10;A: Orders arrive in 3-5 working days." />

            <div class="flex flex-wrap items-center justify-between gap-2">
                <flux:text class="text-xs text-muted">
                    Changes apply to your next question.
                </flux:text>

                <flux:button size="sm" variant="ghost" wire:click="restoreSampleFaq">
                    Use sample FAQ
                </flux:button>
            </div>
        </flux:card>
    </div>
</div>
