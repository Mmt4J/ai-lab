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

<div class="mx-auto w-full max-w-6xl space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        {{-- Shared intro. --}}
        <x-lab-header title="Support ticket classifier"
            description="Classify incoming support tickets with AI, then manage, close, and reopen them from one place." />

        {{--
            Two-tab switcher. Both panels stay mounted below, so switching tabs
            preserves form input, filters, and scroll state.
        --}}
        <div class="flex rounded-lg border border-line bg-card p-1 shadow-sm dark:bg-white/5" role="tablist"
            aria-label="Support ticket sections">
            <button type="button" role="tab" id="tickets-tab-classify"
                aria-selected="{{ $tab === 'classify' }}" aria-controls="tickets-panel-classify"
                wire:click="$set('tab', 'classify')"
                @class([
                    'rounded-md px-4 py-1.5 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500',
                    'bg-brand-700 text-white shadow-sm dark:bg-brand-500 dark:text-brand-950' => $tab === 'classify',
                    'text-ink/65 hover:bg-brand-50 hover:text-brand-800 dark:text-white/60 dark:hover:bg-brand-950 dark:hover:text-brand-200' => $tab !== 'classify',
                ])>
                Classify ticket
            </button>
            <button type="button" role="tab" id="tickets-tab-manage"
                aria-selected="{{ $tab === 'manage' }}" aria-controls="tickets-panel-manage"
                wire:click="$set('tab', 'manage')"
                @class([
                    'rounded-md px-4 py-1.5 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500',
                    'bg-brand-700 text-white shadow-sm dark:bg-brand-500 dark:text-brand-950' => $tab === 'manage',
                    'text-ink/65 hover:bg-brand-50 hover:text-brand-800 dark:text-white/60 dark:hover:bg-brand-950 dark:hover:text-brand-200' => $tab !== 'manage',
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
