<?php

use App\Jobs\ClassifySupportTicket;
use App\Models\SupportTicketClassifier;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';

    public string $categoryFilter = '';

    public string $urgencyFilter = '';

    public string $statusFilter = '';

    /** The ticket currently open in the details modal. */
    public ?int $viewingTicketId = null;

    /**
     * The status change awaiting confirmation:
     * ['id' => int, 'action' => 'close'|'reopen'].
     *
     * @var array{id: int, action: string}|null
     */
    public ?array $pendingStatusChange = null;

    /** The ticket whose classification is currently being retried. */
    public ?int $processingTicketId = null;

    /** The ticket awaiting confirmation before being moved to the trash. */
    public ?int $pendingDeleteId = null;

    public function mount(): void
    {
        $this->refreshStatistics();
    }

    /**
     * Database-backed dashboard statistics, refreshed after every change.
     */
    public function refreshStatistics(): void
    {
        $row = SupportTicketClassifier::query()
            ->selectRaw('
                count(*) as total_count,
                sum(case when status = ? then 1 else 0 end) as open_count,
                sum(case when status = ? then 1 else 0 end) as closed_count,
                sum(case when urgency_level = ? then 1 else 0 end) as high_urgency_count,
                sum(case when ticket_category = ? then 1 else 0 end) as billing_count,
                sum(case when ticket_category = ? then 1 else 0 end) as technical_count,
                sum(case when ticket_category = ? then 1 else 0 end) as complaint_count
            ', [
                SupportTicketClassifier::STATUS_OPEN,
                SupportTicketClassifier::STATUS_CLOSED,
                'high',
                'billing',
                'technical',
                'complaint',
            ])
            ->toBase()
            ->first();

        $this->stats = [
            'total' => (int) ($row->total_count ?? 0),
            'open' => (int) ($row->open_count ?? 0),
            'closed' => (int) ($row->closed_count ?? 0),
            'high_urgency' => (int) ($row->high_urgency_count ?? 0),
            'billing' => (int) ($row->billing_count ?? 0),
            'technical' => (int) ($row->technical_count ?? 0),
            'complaint' => (int) ($row->complaint_count ?? 0),
            'trashed' => SupportTicketClassifier::onlyTrashed()->count(),
            'pending' => SupportTicketClassifier::query()->pendingClassification()->count(),
        ];
    }

    public array $stats = [
        'total' => 0,
        'open' => 0,
        'closed' => 0,
        'high_urgency' => 0,
        'billing' => 0,
        'technical' => 0,
        'complaint' => 0,
        'trashed' => 0,
        'pending' => 0,
    ];

    /**
     * The filtered, paginated ticket list shown in the table.
     */
    #[Computed]
    public function tickets()
    {
        $onlyTrashed = $this->statusFilter === 'trashed';

        return SupportTicketClassifier::query()
            ->when($onlyTrashed, fn ($query) => $query->onlyTrashed())
            ->search($this->search)
            ->when($this->categoryFilter !== '', fn ($query) => $query->where('ticket_category', $this->categoryFilter))
            ->when($this->urgencyFilter !== '', fn ($query) => $query->where('urgency_level', $this->urgencyFilter))
            ->when($this->statusFilter !== '' && ! $onlyTrashed, fn ($query) => $query->where('status', $this->statusFilter))
            ->latest()
            ->paginate(10);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'categoryFilter', 'urgencyFilter', 'statusFilter'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'categoryFilter', 'urgencyFilter', 'statusFilter']);
        $this->resetPage();
    }

    public function hasActiveFilters(): bool
    {
        return $this->search !== ''
            || $this->categoryFilter !== ''
            || $this->urgencyFilter !== ''
            || $this->statusFilter !== '';
    }

    /**
     * Open the details modal for a ticket.
     */
    public function viewTicket(int $id): void
    {
        if (SupportTicketClassifier::withTrashed()->find($id) === null) {
            $this->dispatch('toast', heading: 'Ticket not found', text: 'The ticket no longer exists.', variant: 'warning');

            return;
        }

        $this->viewingTicketId = $id;
        $this->dispatch('modal-show', name: 'ticket-details');
    }

    /**
     * Open the details modal for a ticket requested by another component.
     */
    #[On('open-ticket')]
    public function openTicket(int $ticketId): void
    {
        $this->viewTicket($ticketId);
    }

    /**
     * Refresh the statistics when tickets are created or updated elsewhere.
     */
    #[On('tickets-updated')]
    public function onTicketsUpdated(): void
    {
        $this->refreshStatistics();
    }

    /**
     * Ask for confirmation before closing or reopening a ticket.
     */
    public function requestStatusChange(int $id): void
    {
        $ticket = SupportTicketClassifier::find($id);

        if ($ticket === null) {
            $this->dispatch('toast', heading: 'Ticket not found', text: 'The ticket no longer exists.', variant: 'warning');

            return;
        }

        $this->pendingStatusChange = [
            'id' => $ticket->id,
            'action' => $ticket->isClosed() ? 'reopen' : 'close',
        ];

        $this->dispatch('modal-show', name: 'confirm-status-change');
    }

    /**
     * Apply the confirmed close/reopen action with state guards.
     */
    public function confirmStatusChange(): void
    {
        if ($this->pendingStatusChange === null) {
            return;
        }

        $id = $this->pendingStatusChange['id'];
        $action = $this->pendingStatusChange['action'];
        $this->pendingStatusChange = null;

        $this->dispatch('modal-close', name: 'confirm-status-change');

        $ticket = SupportTicketClassifier::find($id);

        if ($ticket === null) {
            $this->dispatch('toast', heading: 'Ticket not found', text: 'The ticket no longer exists.', variant: 'warning');

            return;
        }

        if ($action === 'close') {
            if ($ticket->isClosed()) {
                $this->dispatch('toast', heading: 'Already closed', text: "Ticket #{$id} is already closed.", variant: 'info');

                return;
            }

            $ticket->update([
                'status' => SupportTicketClassifier::STATUS_CLOSED,
                'closed_at' => now(),
            ]);

            $this->dispatch('toast', heading: 'Ticket closed', text: "Ticket #{$id} has been closed.", variant: 'success');
        } else {
            if (! $ticket->isClosed()) {
                $this->dispatch('toast', heading: 'Already open', text: "Ticket #{$id} is already open.", variant: 'info');

                return;
            }

            $ticket->update([
                'status' => SupportTicketClassifier::STATUS_OPEN,
                'closed_at' => null,
            ]);

            $this->dispatch('toast', heading: 'Ticket reopened', text: "Ticket #{$id} has been reopened.", variant: 'success');
        }

        $this->refreshStatistics();
    }

    public function clearPendingStatusChange(): void
    {
        $this->pendingStatusChange = null;
    }

    public function cancelViewing(): void
    {
        $this->viewingTicketId = null;
    }

    /**
     * Ask for confirmation before moving a ticket to the trash.
     */
    public function requestDelete(int $id): void
    {
        if (SupportTicketClassifier::find($id) === null) {
            $this->dispatch('toast', heading: 'Ticket not found', text: 'The ticket no longer exists.', variant: 'warning');

            return;
        }

        $this->pendingDeleteId = $id;
        $this->dispatch('modal-show', name: 'confirm-delete');
    }

    /**
     * Soft-delete the confirmed ticket so it can be restored later.
     */
    public function confirmDelete(): void
    {
        if ($this->pendingDeleteId === null) {
            return;
        }

        $id = $this->pendingDeleteId;
        $this->pendingDeleteId = null;

        $this->dispatch('modal-close', name: 'confirm-delete');

        $ticket = SupportTicketClassifier::find($id);

        if ($ticket === null) {
            $this->dispatch('toast', heading: 'Ticket not found', text: 'The ticket no longer exists.', variant: 'warning');

            return;
        }

        $ticket->delete();

        $this->dispatch('toast', heading: 'Ticket moved to trash', text: "Ticket #{$id} can be restored from the trash.", variant: 'success');

        $this->refreshStatistics();
    }

    public function clearPendingDelete(): void
    {
        $this->pendingDeleteId = null;
    }

    /**
     * Restore a soft-deleted ticket back into the active list.
     */
    public function restoreTicket(int $id): void
    {
        $ticket = SupportTicketClassifier::onlyTrashed()->find($id);

        if ($ticket === null) {
            $this->dispatch('toast', heading: 'Ticket not found', text: 'That ticket is not in the trash.', variant: 'warning');

            return;
        }

        $ticket->restore();

        $this->dispatch('toast', heading: 'Ticket restored', text: "Ticket #{$id} has been restored.", variant: 'success');

        $this->refreshStatistics();
    }

    /**
     * Queue a classification attempt for an unclassified ticket using its
     * saved original subject and message. Never creates a new ticket.
     */
    public function retryClassification(int $id): void
    {
        if ($this->processingTicketId !== null) {
            return;
        }

        $ticket = SupportTicketClassifier::find($id);

        if ($ticket === null) {
            $this->dispatch('toast', heading: 'Ticket not found', text: 'The ticket no longer exists.', variant: 'warning');

            return;
        }

        if ($ticket->isClassified()) {
            $this->dispatch('toast', heading: 'Already classified', text: "Ticket #{$id} is already classified.", variant: 'info');

            return;
        }

        if ($ticket->isClassificationPending()) {
            $this->dispatch('toast', heading: 'Already in progress', text: "Ticket #{$id} is already being classified.", variant: 'info');

            return;
        }

        $this->processingTicketId = $id;

        $ticket->markClassificationRequested();

        ClassifySupportTicket::dispatch($ticket->id);

        $this->processingTicketId = null;
        $this->refreshStatistics();

        $ticket->refresh();

        if ($ticket->isClassified()) {
            $this->dispatch('toast', heading: 'Ticket classified', text: "Ticket #{$id} has been classified.", variant: 'success');
        } elseif ($ticket->hasClassificationFailed()) {
            $this->dispatch('toast', heading: 'Classification failed', text: (string) $ticket->classification_error, variant: 'danger');
        } else {
            $this->dispatch('toast', heading: 'Classification queued', text: "Ticket #{$id} is being classified.", variant: 'info');
        }
    }
};
?>

<div class="space-y-6" @if ($stats['pending'] > 0) wire:poll.5s="refreshStatistics" @endif>
    {{-- Dashboard statistics --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-5" data-test="ticket-stats">
        <flux:card class="p-4">
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Total tickets</flux:text>
            <p class="mt-1 text-2xl font-semibold" data-test="stat-total">{{ $stats['total'] }}</p>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Open</flux:text>
            <p class="mt-1 text-2xl font-semibold text-green-600 dark:text-green-400" data-test="stat-open">{{ $stats['open'] }}</p>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Closed</flux:text>
            <p class="mt-1 text-2xl font-semibold text-zinc-500 dark:text-zinc-400" data-test="stat-closed">{{ $stats['closed'] }}</p>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">High urgency</flux:text>
            <p class="mt-1 text-2xl font-semibold text-red-600 dark:text-red-400" data-test="stat-high-urgency">{{ $stats['high_urgency'] }}</p>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Trashed</flux:text>
            <p class="mt-1 text-2xl font-semibold text-zinc-500 dark:text-zinc-400" data-test="stat-trashed">{{ $stats['trashed'] }}</p>
        </flux:card>
    </div>

    <flux:card class="flex flex-wrap items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
        <flux:text class="font-medium text-zinc-700 dark:text-zinc-300">By category:</flux:text>
        <flux:badge color="cyan">Billing {{ $stats['billing'] }}</flux:badge>
        <flux:badge color="amber">Technical {{ $stats['technical'] }}</flux:badge>
        <flux:badge color="red">Complaint {{ $stats['complaint'] }}</flux:badge>
    </flux:card>

    {{-- Search and filters --}}
    <flux:card class="space-y-4">
        <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-1">
                <flux:input wire:model.live.debounce.400ms="search" label="Search"
                    placeholder="Name, company, email, subject..." icon="magnifying-glass" clearable />
            </div>
            <div>
                <flux:select wire:model.live="categoryFilter" label="Category" placeholder="All categories">
                    <flux:select.option value="billing">Billing</flux:select.option>
                    <flux:select.option value="technical">Technical</flux:select.option>
                    <flux:select.option value="complaint">Complaint</flux:select.option>
                </flux:select>
            </div>
            <div>
                <flux:select wire:model.live="urgencyFilter" label="Urgency" placeholder="All urgency levels">
                    <flux:select.option value="low">Low</flux:select.option>
                    <flux:select.option value="medium">Medium</flux:select.option>
                    <flux:select.option value="high">High</flux:select.option>
                </flux:select>
            </div>
            <div>
                <flux:select wire:model.live="statusFilter" label="Status" placeholder="All statuses">
                    <flux:select.option value="open">Open</flux:select.option>
                    <flux:select.option value="closed">Closed</flux:select.option>
                    <flux:select.option value="trashed">Trashed</flux:select.option>
                </flux:select>
            </div>
        </div>

        @if ($this->hasActiveFilters())
            <div class="flex items-center justify-between gap-2">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    Filters applied &mdash; {{ $this->tickets->total() }} matching ticket(s).
                </flux:text>
                <flux:button wire:click="clearFilters" size="sm" variant="ghost" icon="x-mark">
                    Clear filters
                </flux:button>
            </div>
        @endif
    </flux:card>

    {{-- Ticket table --}}
    <flux:card>
        @if ($this->tickets->count() === 0)
            <div class="flex flex-col items-center justify-center gap-3 py-12 text-center">
                <flux:icon.inbox class="size-10 text-zinc-400" />
                @if ($this->hasActiveFilters())
                    <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">No tickets match your search or filters.</p>
                    <flux:button wire:click="clearFilters" size="sm" variant="outline">Clear filters</flux:button>
                @else
                    <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">No tickets yet.</p>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Submit a ticket in the classifier to see it here.</flux:text>
                @endif
            </div>
        @else
            <flux:table :paginate="$this->tickets">
                <flux:table.columns>
                    <flux:table.column>ID</flux:table.column>
                    <flux:table.column>Customer</flux:table.column>
                    <flux:table.column>Subject</flux:table.column>
                    <flux:table.column>Category</flux:table.column>
                    <flux:table.column>Urgency</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Created</flux:table.column>
                    <flux:table.column align="end">Actions</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->tickets as $ticket)
                        @php
                            $categoryColor = match ($ticket->ticket_category) {
                                'billing' => 'cyan',
                                'technical' => 'amber',
                                'complaint' => 'red',
                                default => null,
                            };
                            $urgencyColor = match ($ticket->urgency_level) {
                                'high' => 'red',
                                'medium' => 'amber',
                                'low' => 'green',
                                default => null,
                            };
                        @endphp

                        <flux:table.row :key="$ticket->id">
                            <flux:table.cell variant="strong">#{{ $ticket->id }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="min-w-0">
                                    <p class="truncate font-medium">{{ $ticket->customer_name ?: 'Anonymous' }}</p>
                                    @if ($ticket->company_name)
                                        <p class="truncate text-xs text-zinc-400 dark:text-zinc-500">{{ $ticket->company_name }}</p>
                                    @endif
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <span class="block max-w-52 truncate" title="{{ $ticket->ticket_subject }}">{{ $ticket->ticket_subject }}</span>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($ticket->ticket_category)
                                    <flux:badge :color="$categoryColor">{{ ucfirst($ticket->ticket_category) }}</flux:badge>
                                @elseif ($ticket->isClassificationPending())
                                    <flux:badge color="amber">Classifying&hellip;</flux:badge>
                                @elseif ($ticket->hasClassificationFailed())
                                    <flux:badge color="red">Failed</flux:badge>
                                @else
                                    <flux:text class="text-xs text-zinc-400 dark:text-zinc-500">Unclassified</flux:text>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($ticket->urgency_level)
                                    <flux:badge :color="$urgencyColor">{{ ucfirst($ticket->urgency_level) }}</flux:badge>
                                @else
                                    <flux:text class="text-xs text-zinc-400 dark:text-zinc-500">&mdash;</flux:text>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($ticket->trashed())
                                    <flux:badge color="red">Trashed</flux:badge>
                                @else
                                    <flux:badge :color="$ticket->isClosed() ? null : 'green'">{{ ucfirst($ticket->status) }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $ticket->created_at->format('M j, Y') }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal"
                                        :aria-label="'Actions for ticket #'.$ticket->id" />
                                    <flux:menu>
                                        <flux:menu.item wire:click="viewTicket({{ $ticket->id }})" icon="eye">
                                            View details
                                        </flux:menu.item>

                                        @if ($ticket->trashed())
                                            <flux:menu.item wire:click="restoreTicket({{ $ticket->id }})"
                                                icon="arrow-uturn-left">
                                                Restore ticket
                                            </flux:menu.item>
                                        @else
                                            @unless ($ticket->isClassified())
                                                @if (! $ticket->isClassificationPending())
                                                    <flux:menu.item wire:click="retryClassification({{ $ticket->id }})"
                                                        icon="arrow-path">
                                                        Retry classification
                                                    </flux:menu.item>
                                                @endif
                                            @endunless

                                            @if ($ticket->isClosed())
                                                <flux:menu.item wire:click="requestStatusChange({{ $ticket->id }})"
                                                    icon="arrow-uturn-left">
                                                    Reopen ticket
                                                </flux:menu.item>
                                            @else
                                                <flux:menu.item wire:click="requestStatusChange({{ $ticket->id }})"
                                                    icon="check-circle">
                                                    Close ticket
                                                </flux:menu.item>
                                            @endif

                                            <flux:menu.item wire:click="requestDelete({{ $ticket->id }})"
                                                icon="trash" variant="danger">
                                                Move to trash
                                            </flux:menu.item>
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>

    {{-- Ticket details modal --}}
    @php
        $viewingTicket = $viewingTicketId !== null ? SupportTicketClassifier::withTrashed()->find($viewingTicketId) : null;
    @endphp

    <flux:modal name="ticket-details" x-on:close="$wire.cancelViewing()" class="space-y-5" scroll="body">
        @if ($viewingTicket !== null)
            <div class="flex items-start justify-between gap-4">
                <div class="space-y-1">
                    <flux:heading size="lg">Ticket #{{ $viewingTicket->id }}</flux:heading>
                    <flux:text class="text-sm text-muted">{{ $viewingTicket->ticket_subject }}</flux:text>
                </div>
                @if ($viewingTicket->trashed())
                    <flux:badge color="red">Trashed</flux:badge>
                @else
                    <flux:badge :color="$viewingTicket->isClosed() ? null : 'green'">{{ ucfirst($viewingTicket->status) }}</flux:badge>
                @endif
            </div>

            <div class="space-y-4">
                {{-- Customer details --}}
                <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800/50">
                    <flux:heading size="sm" class="text-zinc-700 dark:text-zinc-300">Customer details</flux:heading>
                    <dl class="mt-2 grid gap-2 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-xs text-zinc-500 dark:text-zinc-400">Name</dt>
                            <dd class="font-medium">{{ $viewingTicket->customer_name ?: 'Not provided' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-500 dark:text-zinc-400">Company</dt>
                            <dd class="font-medium">{{ $viewingTicket->company_name ?: 'Not provided' }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-xs text-zinc-500 dark:text-zinc-400">Email</dt>
                            <dd class="font-medium">{{ $viewingTicket->email ?: 'Not provided' }}</dd>
                        </div>
                    </dl>
                </div>

                {{-- Original message --}}
                <div>
                    <flux:heading size="sm" class="text-zinc-700 dark:text-zinc-300">Original message</flux:heading>
                    <p class="mt-2 whitespace-pre-line rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-700">{{ $viewingTicket->ticket_message }}</p>
                </div>

                {{-- AI classification --}}
                <div>
                    <flux:heading size="sm" class="text-zinc-700 dark:text-zinc-300">AI classification</flux:heading>
                    @if ($viewingTicket->isClassified())
                        <div class="mt-2 space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:badge :color="match ($viewingTicket->ticket_category) { 'billing' => 'cyan', 'technical' => 'amber', 'complaint' => 'red', default => null }">
                                    {{ ucfirst($viewingTicket->ticket_category) }}
                                </flux:badge>
                                <flux:badge :color="match ($viewingTicket->urgency_level) { 'high' => 'red', 'medium' => 'amber', 'low' => 'green', default => null }">
                                    {{ ucfirst($viewingTicket->urgency_level) }} urgency
                                </flux:badge>
                            </div>
                            <p class="text-sm">{{ $viewingTicket->ai_summary }}</p>
                        </div>
                    @elseif ($viewingTicket->isClassificationPending())
                        <div class="mt-2 flex items-center gap-2">
                            <flux:icon.arrow-path variant="micro" class="size-4 animate-spin text-amber-600 dark:text-amber-400" />
                            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">Classification is in progress&hellip;</flux:text>
                        </div>
                    @elseif (! $viewingTicket->trashed())
                        <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">This ticket has not been classified yet.</flux:text>
                            <flux:button wire:click="retryClassification({{ $viewingTicket->id }})" size="sm"
                                variant="outline" icon="arrow-path" :disabled="$processingTicketId !== null">
                                Retry classification
                            </flux:button>
                        </div>
                    @else
                        <flux:text class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">This ticket is in the trash and has not been classified.</flux:text>
                    @endif
                </div>

                {{-- Timestamps --}}
                <dl class="grid gap-2 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-xs text-zinc-500 dark:text-zinc-400">Created</dt>
                        <dd class="font-medium">{{ $viewingTicket->created_at->format('M j, Y H:i') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-zinc-500 dark:text-zinc-400">Classified</dt>
                        <dd class="font-medium">{{ $viewingTicket->classified_at?->format('M j, Y H:i') ?? 'Not classified' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-zinc-500 dark:text-zinc-400">Closed</dt>
                        <dd class="font-medium">{{ $viewingTicket->closed_at?->format('M j, Y H:i') ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="flex justify-end gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                @if ($viewingTicket->trashed())
                    <flux:button wire:click="restoreTicket({{ $viewingTicket->id }})" variant="filled"
                        icon="arrow-uturn-left">
                        Restore ticket
                    </flux:button>
                @elseif ($viewingTicket->isClosed())
                    <flux:button wire:click="requestStatusChange({{ $viewingTicket->id }})" variant="outline"
                        icon="arrow-uturn-left">
                        Reopen ticket
                    </flux:button>
                @else
                    <flux:button wire:click="requestStatusChange({{ $viewingTicket->id }})" variant="outline"
                        icon="check-circle">
                        Close ticket
                    </flux:button>
                @endif
            </div>
        @else
            <flux:heading size="lg">Ticket not found</flux:heading>
            <flux:text class="text-sm text-muted">This ticket no longer exists.</flux:text>
        @endif
    </flux:modal>

    {{-- Close/reopen confirmation modal --}}
    <flux:modal name="confirm-status-change" x-on:close="$wire.clearPendingStatusChange()" scroll="body">
        @if ($pendingStatusChange !== null)
            @php
                $isClosing = $pendingStatusChange['action'] === 'close';
            @endphp

            <div class="space-y-4">
                <flux:heading size="lg">
                    {{ $isClosing ? 'Close ticket?' : 'Reopen ticket?' }}
                </flux:heading>

                <flux:text class="text-sm text-muted">
                    @if ($isClosing)
                        Ticket #{{ $pendingStatusChange['id'] }} will be marked as closed. You can reopen it later.
                    @else
                        Ticket #{{ $pendingStatusChange['id'] }} will be marked as open again.
                    @endif
                </flux:text>

                <div class="flex justify-end gap-3">
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>

                    <flux:button wire:click="confirmStatusChange" variant="filled"
                        :color="$isClosing ? 'red' : 'green'">
                        {{ $isClosing ? 'Close ticket' : 'Reopen ticket' }}
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- Move to trash confirmation modal --}}
    <flux:modal name="confirm-delete" x-on:close="$wire.clearPendingDelete()" scroll="body">
        @if ($pendingDeleteId !== null)
            <div class="space-y-4">
                <flux:heading size="lg">Move ticket to trash?</flux:heading>

                <flux:text class="text-sm text-muted">
                    Ticket #{{ $pendingDeleteId }} will be moved to the trash. You can restore it later from the
                    Trashed status filter.
                </flux:text>

                <div class="flex justify-end gap-3">
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>

                    <flux:button wire:click="confirmDelete" variant="filled" color="red">
                        Move to trash
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
