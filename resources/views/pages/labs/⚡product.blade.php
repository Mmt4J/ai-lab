<?php

use Livewire\Component;

new class extends Component {
    public string $name = '';
    public string $keywords = '';
    public string $tone = 'Professional';
    public string $description = '';

    public function generateDescription(): void
    {
        $this->description = app(\App\Services\Ai::class)->generateProductDescription($this->name, $this->keywords, $this->tone) ?: 'No description generated. Please try again.';
    }

    public function generateProductDescription($name, $keywords, $tone)
    {
        return null; // Temporary placeholder
    }
};
?>

<div class="max-w-2xl space-y-4">
    <flux:heading size="xl">
        Product Description Generator
    </flux:heading>

    <form wire:submit="generateDescription" class="space-y-3">
        <flux:input wire:model="name" label="Product Name" placeholder="Product name..." required />

        <flux:select wire:model="tone" label="Product Tone" placeholder="Select a tone">
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

        <flux:input wire:model="keywords" label="Keywords"
            placeholder="e.g. premium, durable, handmade, durable, Lagos, colourful" />

        {{-- <flux:textarea wire:model="description" label="Generated Description" rows="7"
            placeholder="Your product description will appear here..." /> --}}

        <flux:button type="submit" variant="primary">
            Generate Description
        </flux:button>
    </form>

    @if ($description)
        <div x-data="{ copied: false }" class="bg-card border border-line rounded-lg p-4 space-y-3">
            <flux:heading size="lg">
                Generated Description
            </flux:heading>

            <p class="text-sm leading-relaxed whitespace-pre-line">
                {{ $description }}
            </p>

            <button type="button"
                @click="navigator.clipboard.writeText($refs.description.innerText);
                copied = true;
                setTimeout(() => copied = false, 1500)"
                class="text-xs border border-line rounded-md px-3 py-1.5 hover:bg-line/60"
                x-text="copied ? 'Copied!' : 'Copy text'"></button>

            <div x-ref="description" class="hidden">{{ $description }}</div>
        </div>
    @endif
</div>
