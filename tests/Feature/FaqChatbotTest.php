<?php

use App\Ai\Agents\FaqAssistant;
use App\Models\User;
use Laravel\Ai\Exceptions\RateLimitedException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function faqChatComponent(): Testable
{
    return Livewire::actingAs(User::factory()->create())->test('pages::labs.faq');
}

test('the faq page requires authentication', function () {
    $this->get(route('labs.faq'))->assertRedirect(route('login'));
});

test('authenticated users can view the faq page', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('labs.faq'))
        ->assertOk()
        ->assertSee('FAQ Chatbot')
        ->assertSee('FAQ knowledge base');
});

test('a question is required before prompting the assistant', function () {
    FaqAssistant::fake()->preventStrayPrompts();

    faqChatComponent()
        ->set('question', '')
        ->call('send')
        ->assertHasErrors(['question' => 'required'])
        ->assertSet('messages', []);

    FaqAssistant::assertNeverPrompted();
});

test('a faq is required before prompting the assistant', function () {
    FaqAssistant::fake()->preventStrayPrompts();

    faqChatComponent()
        ->set('faq', '')
        ->set('question', 'How long does delivery take?')
        ->call('send')
        ->assertHasErrors(['faq' => 'required'])
        ->assertSet('messages', []);

    FaqAssistant::assertNeverPrompted();
});

test('a question is answered from the faq and added to the conversation', function () {
    $faq = 'Q: How long does delivery take? A: Standard delivery takes 3-5 working days.';

    FaqAssistant::fake(['Standard delivery takes 3-5 working days.']);

    faqChatComponent()
        ->set('faq', $faq)
        ->set('question', 'How long does delivery take?')
        ->call('send')
        ->assertHasNoErrors()
        ->assertSet('question', '')
        ->assertSet('messages', [
            ['role' => 'user', 'content' => 'How long does delivery take?'],
            ['role' => 'assistant', 'content' => 'Standard delivery takes 3-5 working days.'],
        ])
        ->assertSee('Standard delivery takes 3-5 working days.');

    FaqAssistant::assertPrompted(function ($prompt) use ($faq): bool {
        return $prompt->contains('How long does delivery take?')
            && str_contains($prompt->agent->instructions(), '<faq>')
            && str_contains($prompt->agent->instructions(), $faq);
    });
});

test('earlier turns are sent as context with the next question', function () {
    FaqAssistant::fake([
        'Standard delivery takes 3-5 working days.',
        'Gift wrapping costs 5 USD.',
    ]);

    $component = faqChatComponent()
        ->set('faq', 'Q: How long does delivery take? A: Standard delivery takes 3-5 working days.')
        ->set('question', 'How long does delivery take?')
        ->call('send')
        ->set('question', 'Do you gift wrap?')
        ->call('send')
        ->assertHasNoErrors();

    expect($component->get('messages'))->toHaveCount(4);

    FaqAssistant::assertPrompted(function ($prompt): bool {
        if (! $prompt->contains('Do you gift wrap?')) {
            return false;
        }

        $history = collect($prompt->agent->messages());

        return $history->count() === 2
            && $history->contains(fn ($message): bool => $message->role->value === 'user'
                && $message->content === 'How long does delivery take?')
            && $history->contains(fn ($message): bool => $message->role->value === 'assistant'
                && $message->content === 'Standard delivery takes 3-5 working days.');
    });
});

test('a failed request leaves the conversation unchanged and keeps the question for a retry', function () {
    FaqAssistant::fake(fn () => throw RateLimitedException::forProvider('groq'));

    faqChatComponent()
        ->set('question', 'How long does delivery take?')
        ->call('send')
        ->assertSet('messages', [])
        ->assertSet('question', 'How long does delivery take?')
        ->assertSee('rate limited');
});

test('starting a new chat clears the conversation but keeps the faq', function () {
    FaqAssistant::fake(['Standard delivery takes 3-5 working days.']);

    faqChatComponent()
        ->set('faq', 'Q: How long does delivery take? A: Standard delivery takes 3-5 working days.')
        ->set('question', 'How long does delivery take?')
        ->call('send')
        ->assertSet('messages', fn (array $messages): bool => count($messages) === 2)
        ->call('resetConversation')
        ->assertSet('messages', [])
        ->assertSet('question', '')
        ->assertSee('Ask about anything in your FAQ');
});

test('the sample faq can be restored after it is replaced', function () {
    $component = faqChatComponent()->set('faq', 'A custom FAQ.');

    $component->call('restoreSampleFaq')
        ->assertSet('faq', fn (string $faq): bool => str_contains($faq, 'What is your returns policy?'));
});

test('a starter question can be asked directly', function () {
    FaqAssistant::fake(['We ship to over 40 countries.']);

    faqChatComponent()
        ->call('ask', 'Do you ship internationally?')
        ->assertHasNoErrors()
        ->assertSet('messages', fn (array $messages): bool => count($messages) === 2);

    FaqAssistant::assertPrompted('Do you ship internationally?');
});
