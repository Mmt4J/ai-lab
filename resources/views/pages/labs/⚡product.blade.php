<?php

use Livewire\Component;
use App\Ai\Agents\ProductDescriptionGenerator;

new class extends Component {
    public string $name = '';
    public string $keywords = '';
    public string $tone = 'Professional';
    public string $description = '';

    public function generateDescription(): void
    {
        $agent = new ProductDescriptionGenerator();
        $prompt = "Generate a product description for:\n\nProduct: {$this->name}\nKeywords: {$this->keywords}\nTone: {$this->tone}";
        $response = $agent->prompt($prompt);
        $this->description = $response->text ?: 'No description generated. Please try again.';
    }
};
?>

<div class="mx-auto w-full max-w-3xl space-y-6">
    {{-- Same page intro as every other lab. --}}
    <x-lab-header title="Product description generator"
        description="Name, a few keywords, a tone. Change the tone and compare the results." />

    <form wire:submit="generateDescription">
        <flux:card class="space-y-4 border-line! bg-card!">
            {{-- Name and tone sit side by side on wider screens. --}}
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="name" label="Product name" placeholder="e.g. Ankara tote bag" required />

                <flux:select wire:model="tone" label="Tone" placeholder="Select a tone">
                    <flux:select.option value="Luxury">Luxury</flux:select.option>
                    <flux:select.option value="Playful">Playful</flux:select.option>
                    <flux:select.option value="Professional">Professional</flux:select.option>
                    <flux:select.option value="Casual">Casual</flux:select.option>
                    <flux:select.option value="Friendly">Friendly</flux:select.option>
                    <flux:select.option value="Bold">Bold</flux:select.option>
                    <flux:select.option value="Elegant">Elegant</flux:select.option>
                    <flux:select.option value="Minimalist">Minimalist</flux:select.option>
                    <flux:select.option value="Quirky">Quirky</flux:select.option>
                    <flux:select.option value="Sophisticated">Sophisticated</flux:select.option>
                    <flux:select.option value="Whimsical">Whimsical</flux:select.option>
                </flux:select>
            </div>

            <flux:input wire:model="keywords" label="Keywords"
                placeholder="e.g. premium, durable, handmade, Lagos, colourful" required />

            <div class="flex flex-wrap items-center gap-3">
                {{-- A primary button uses the shared green accent automatically. --}}
                <flux:button type="submit" variant="primary" icon="sparkles" wire:loading.attr="disabled"
                    wire:target="generateDescription">
                    <span wire:loading.remove wire:target="generateDescription">Write description</span>
                    <span wire:loading wire:target="generateDescription">Writing&hellip;</span>
                </flux:button>

                <flux:text class="text-xs text-muted">
                    Change the tone and generate again to compare.
                </flux:text>
            </div>
        </flux:card>
    </form>

    {{-- The result card only exists after a description has been generated. --}}
    @if ($description)
        <flux:card class="space-y-4 border-line! bg-card!" x-data="{ copied: false }">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span class="flex size-8 items-center justify-center rounded-md bg-brand-50 text-brand-700 dark:bg-brand-950 dark:text-brand-300">
                        <flux:icon.document-text class="size-4" />
                    </span>

                    <flux:heading size="lg" class="font-display">
                        Generated description
                    </flux:heading>
                </div>

                <flux:badge color="teal" size="sm">
                    {{ $tone }}
                </flux:badge>
            </div>

            <p class="text-sm leading-relaxed whitespace-pre-line">
                {{ $description }}
            </p>

            {{--
                Copy is pure client-side state, so Alpine handles it.
                The hidden element keeps the raw text available even if the
                visible paragraph is styled or wrapped differently.
            --}}
            <div class="flex justify-end border-t border-line pt-3">
                <flux:button type="button" variant="ghost" size="sm" icon="clipboard"
                    x-on:click="navigator.clipboard.writeText($refs.description.innerText); copied = true; setTimeout(() => copied = false, 1500)">
                    <span x-text="copied ? 'Copied!' : 'Copy text'">Copy text</span>
                </flux:button>
            </div>

            <div x-ref="description" class="hidden">{{ $description }}</div>
        </flux:card>
    @endif
</div>
