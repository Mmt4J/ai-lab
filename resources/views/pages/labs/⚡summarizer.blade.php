<?php

use Livewire\Component;
use Livewire\Attributes\Validate;

new class extends Component {
    //
    #[Validate('required|min:50')]
    public string $text = '';
    public array $bullets = [];

    public function summarize(): void
    {
        $this->validate();

        $this->bullets = app(\App\Services\Ai::class)->summarize($this->text);
    }
};
?>

<div class="max-w-2xl space-y-4">
    <flux:heading size="xl">Text summarizer</flux:heading>

    <form wire:submit="summarize" class="space-y-3">
        <flux:textarea wire:model="text" rows="7" placeholder="Paste long text..." />
        <flux:button type="submit" variant="primary">Summarize</flux:button>
    </form>

    @foreach ($bullets as $b)
        <p class="text-sm">• {{ $b }}</p>
    @endforeach
</div>
