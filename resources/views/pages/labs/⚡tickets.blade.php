<?php

use Livewire\Component;

new class extends Component {
    /** Which section is visible: "classify" or "manage". */
    public string $tab = 'classify';

    /**
     * Switch to the management section and open the given ticket's details.
     */
    public function showManagement(int $ticketId): void
    {
        $this->tab = 'manage';

        $this->dispatch('open-ticket', ticketId: $ticketId);
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="space-y-1">
            <flux:heading size="xl">Support Ticket Classifier</flux:heading>
            <flux:text class="text-sm text-muted">
                Classify incoming support tickets with AI, then manage, close, and reopen them from one place.
            </flux:text>
        </div>

        <div class="flex rounded-lg border border-zinc-200 p-1 dark:border-zinc-700" role="tablist"
            aria-label="Support ticket sections">
            <button type="button" role="tab" id="tickets-tab-classify"
                aria-selected="{{ $tab === 'classify' }}" aria-controls="tickets-panel-classify"
                wire:click="$set('tab', 'classify')"
                @class([
                    'rounded-md px-4 py-1.5 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500',
                    'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' => $tab === 'classify',
                    'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100' => $tab !== 'classify',
                ])>
                Classify ticket
            </button>
            <button type="button" role="tab" id="tickets-tab-manage"
                aria-selected="{{ $tab === 'manage' }}" aria-controls="tickets-panel-manage"
                wire:click="$set('tab', 'manage')"
                @class([
                    'rounded-md px-4 py-1.5 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500',
                    'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' => $tab === 'manage',
                    'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100' => $tab !== 'manage',
                ])>
                Manage tickets
            </button>
        </div>
    </div>

    {{-- Both sections stay mounted so their state is preserved between tabs --}}
    <div id="tickets-panel-classify" role="tabpanel" aria-labelledby="tickets-tab-classify"
        class="{{ $tab === 'classify' ? '' : 'hidden' }}">
        <livewire:ticket-classifier @view-ticket="showManagement($event.ticketId)" />
    </div>

    <div id="tickets-panel-manage" role="tabpanel" aria-labelledby="tickets-tab-manage"
        class="{{ $tab === 'manage' ? '' : 'hidden' }}">
        <livewire:ticket-dashboard />
    </div>
</div>
