<?php

use Livewire\Component;
use Livewire\Attributes\Validate;
use App\Ai\Agents\Summarizer;

new class extends Component {
    #[Validate('required|min:50')]
    public string $text = '';
    public array $bullets = [];

    public function summarize(): void
    {
        $this->validate();

        $response = new Summarizer()->prompt($this->text);
        $this->bullets = array_filter(array_map('trim', explode("\n", $response->text)), fn($line) => $line !== '');
    }
};
?>

<div class="mx-auto w-full max-w-2xl space-y-6">
    {{-- Shared intro so this lab lines up with the other five. --}}
    <x-lab-header title="Text summarizer" description="Paste long text. Get three bullets back." />

    <form wire:submit="summarize">
        <flux:card class="space-y-4 border-line! bg-card!">
            {{-- Label row doubles as a live character counter. --}}
            <div class="flex flex-wrap items-center justify-between gap-2">
                <label for="summarizer-text" class="text-sm font-medium text-ink dark:text-white">
                    Your text
                </label>

                <flux:text class="text-xs text-muted">
                    {{ number_format(mb_strlen($text)) }} characters
                </flux:text>
            </div>

            {{-- The textarea is bound to the Livewire $text property. --}}
            <flux:textarea id="summarizer-text" wire:model="text" rows="8" maxlength="12000"
                placeholder="Paste an article, email thread, or report here..." required />

            {{-- Server-side validation message for the 50-character minimum. --}}
            <flux:error name="text" />

            <div class="flex flex-wrap items-center gap-3">
                {{-- variant="primary" reads the green accent tokens from app.css. --}}
                <flux:button type="submit" variant="primary" icon="sparkles" wire:loading.attr="disabled"
                    wire:target="summarize">
                    <span wire:loading.remove wire:target="summarize">Summarize</span>
                    <span wire:loading wire:target="summarize">Summarizing&hellip;</span>
                </flux:button>

                <flux:text class="text-xs text-muted">
                    At least 50 characters.
                </flux:text>
            </div>
        </flux:card>
    </form>

    {{-- Results only render once the model has returned at least one bullet. --}}
    @if (count($bullets) > 0)
        <flux:card class="space-y-4 border-line! bg-card!" aria-live="polite">
            <div class="flex items-center gap-2">
                <span class="flex size-8 items-center justify-center rounded-md bg-brand-50 text-brand-700 dark:bg-brand-950 dark:text-brand-300">
                    <flux:icon.sparkles class="size-4" />
                </span>

                <flux:heading size="lg" class="font-display">
                    Summary
                </flux:heading>
            </div>

            {{-- Numbered bullets are easier to scan than a wall of text. --}}
            <ol class="space-y-3">
                @foreach ($bullets as $index => $bullet)
                    <li class="flex gap-3 text-sm leading-relaxed">
                        <span
                            class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-brand-700 text-[10px] font-semibold text-white dark:bg-brand-500 dark:text-brand-950">
                            {{ $loop->iteration }}
                        </span>
                        <span>{{ $bullet }}</span>
                    </li>
                @endforeach
            </ol>
        </flux:card>
    @endif
</div>
