<?php

use App\Jobs\ClassifySupportTicket;
use App\Models\SupportTicketClassifier;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component {
    #[Validate('nullable|string|max:255', message: 'Customer name must be 255 characters or fewer.')]
    public string $customerName = '';

    #[Validate('nullable|string|max:255', message: 'Company name must be 255 characters or fewer.')]
    public string $companyName = '';

    #[Validate('nullable|email|max:255', message: 'Please enter a valid email address.')]
    public string $email = '';

    #[Validate('required|string|min:5|max:255', message: 'A ticket subject of at least 5 characters is required.')]
    public string $ticketSubject = '';

    #[Validate('required|string|min:10|max:5000', message: 'A ticket message of at least 10 characters is required.')]
    public string $ticketMessage = '';

    /** Whether the submit/retry request itself is in flight. */
    public bool $processing = false;

    /** The classified ticket shown in the results panel. */
    public ?SupportTicketClassifier $ticket = null;

    /** A saved ticket whose queued classification failed and can be retried. */
    public ?int $failedTicketId = null;

    /** A saved ticket whose classification is still being processed. */
    public ?int $pendingTicketId = null;

    /** User-facing failure message for the last classification attempt. */
    public string $errorMessage = '';

    /**
     * Save the ticket, then hand it to the queue for classification.
     */
    public function submit(): void
    {
        if ($this->processing) {
            return;
        }

        $this->validate();

        $this->processing = true;
        $this->errorMessage = '';

        try {
            $ticket = SupportTicketClassifier::create([
                'customer_name' => $this->customerName !== '' ? trim($this->customerName) : null,
                'company_name' => $this->companyName !== '' ? trim($this->companyName) : null,
                'email' => $this->email !== '' ? trim($this->email) : null,
                'ticket_subject' => trim($this->ticketSubject),
                'ticket_message' => trim($this->ticketMessage),
                'status' => SupportTicketClassifier::STATUS_OPEN,
            ]);
        } catch (Throwable $e) {
            report($e);

            $this->processing = false;
            $this->errorMessage = 'The ticket could not be saved due to a database error. Please try again.';

            $this->dispatch('toast', heading: 'Ticket not saved', text: $this->errorMessage, variant: 'danger');

            return;
        }

        $ticket->markClassificationRequested();

        ClassifySupportTicket::dispatch($ticket->id);

        $this->ticket = null;
        $this->failedTicketId = null;
        $this->pendingTicketId = $ticket->id;
        $this->processing = false;

        $this->dispatch('tickets-updated');
        $this->dispatch('toast', heading: 'Ticket submitted', text: 'Ticket #'.$ticket->id.' is being classified.', variant: 'info');

        // Resolves immediately when the queue connection runs inline.
        $this->refreshPending();
    }

    /**
     * Poll for the outcome of a queued classification. Called by wire:poll
     * while a ticket is pending and once more right after dispatching.
     */
    public function refreshPending(): void
    {
        if ($this->pendingTicketId === null) {
            return;
        }

        $ticket = SupportTicketClassifier::find($this->pendingTicketId);

        if ($ticket === null) {
            $this->pendingTicketId = null;
            $this->errorMessage = 'The ticket no longer exists.';

            return;
        }

        if ($ticket->isClassified()) {
            $this->pendingTicketId = null;
            $this->ticket = $ticket;
            $this->failedTicketId = null;
            $this->errorMessage = '';

            $this->dispatch('toast', heading: 'Ticket classified', text: 'Ticket #'.$ticket->id.' has been classified.', variant: 'success');

            return;
        }

        if ($ticket->hasClassificationFailed()) {
            $this->pendingTicketId = null;
            $this->ticket = null;
            $this->failedTicketId = $ticket->id;
            $this->errorMessage = (string) $ticket->classification_error;

            $this->dispatch('toast', heading: 'Classification failed', text: $this->errorMessage, variant: 'danger');
        }

        // Otherwise the job is still running and polling continues.
    }

    /**
     * Queue another classification attempt for a ticket whose earlier
     * attempt failed, reusing its saved subject and message.
     */
    public function retryClassification(): void
    {
        if ($this->processing || $this->failedTicketId === null) {
            return;
        }

        $ticket = SupportTicketClassifier::find($this->failedTicketId);

        if ($ticket === null) {
            $this->failedTicketId = null;
            $this->errorMessage = 'The ticket no longer exists.';

            return;
        }

        $this->processing = true;

        $ticket->markClassificationRequested();

        ClassifySupportTicket::dispatch($ticket->id);

        $this->failedTicketId = null;
        $this->pendingTicketId = $ticket->id;
        $this->errorMessage = '';
        $this->processing = false;

        $this->refreshPending();
    }

    /**
     * Reset the form so another ticket can be submitted.
     */
    public function classifyAnother(): void
    {
        $this->reset(['customerName', 'companyName', 'email', 'ticketSubject', 'ticketMessage', 'errorMessage', 'failedTicketId', 'pendingTicketId']);
        $this->ticket = null;
        $this->processing = false;
        $this->resetValidation();
    }

    /**
     * Ask the management dashboard to open this ticket's details.
     */
    public function viewDetails(): void
    {
        if ($this->ticket !== null) {
            $this->dispatch('view-ticket', ticketId: $this->ticket->id);
        }
    }
};
?>

<div class="space-y-6" @if ($pendingTicketId !== null) wire:poll.3s="refreshPending" @endif>
    <flux:card class="space-y-4">
        <div class="space-y-1">
            <flux:heading size="lg">Submit a Support Ticket</flux:heading>
            <flux:text class="text-sm text-muted">
                Enter the customer's details and ticket content. The ticket is saved first, then classified by the AI
                into a category and urgency level with a short summary.
            </flux:text>
        </div>

        <form wire:submit="submit" class="space-y-4" novalidate>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="customerName" label="Customer name" placeholder="Optional" autocomplete="name" />
                <flux:input wire:model="companyName" label="Company name" placeholder="Optional" autocomplete="organization" />
            </div>

            <flux:input wire:model="email" type="email" label="Email address" placeholder="Optional" autocomplete="email" />

            <flux:input wire:model="ticketSubject" label="Ticket subject" placeholder="e.g. Duplicate subscription charge" required />

            <flux:textarea wire:model="ticketMessage" label="Ticket message" rows="6"
                placeholder="Describe the customer's issue and requested resolution..." required />

            <div class="flex flex-wrap gap-3">
                <flux:button type="submit" variant="filled" color="blue" :disabled="$processing" class="data-loading:opacity-50">
                    @if ($processing)
                        <flux:icon.arrow-path variant="micro" class="animate-spin" />
                        Submitting ticket...
                    @else
                        Submit &amp; Classify Ticket
                    @endif
                </flux:button>

                @if ($ticket !== null || $failedTicketId !== null || $pendingTicketId !== null)
                    <flux:button wire:click="classifyAnother" variant="ghost" :disabled="$processing">
                        Classify another ticket
                    </flux:button>
                @endif
            </div>
        </form>

        {{-- Classification is running on the queue --}}
        @if ($pendingTicketId !== null)
            <div class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20"
                role="status" aria-live="polite">
                <flux:icon.arrow-path variant="micro" class="mt-0.5 size-5 shrink-0 animate-spin text-amber-600 dark:text-amber-400" />
                <div class="space-y-1">
                    <p class="text-sm font-medium text-amber-800 dark:text-amber-300">
                        Classifying ticket #{{ $pendingTicketId }}&hellip;
                    </p>
                    <p class="text-sm text-amber-700 dark:text-amber-300">
                        The ticket has been saved. The AI is classifying it in the background — this page updates
                        automatically.
                    </p>
                </div>
            </div>
        @elseif ($errorMessage !== '' && $failedTicketId !== null)
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20" role="alert">
                <div class="flex items-start gap-3">
                    <flux:icon.exclamation-triangle class="mt-0.5 size-5 shrink-0 text-red-500" />
                    <div class="flex-1 space-y-1">
                        <p class="text-sm font-medium text-red-800 dark:text-red-300">
                            Ticket #{{ $failedTicketId }} was saved, but AI classification failed.
                        </p>
                        <p class="text-sm text-red-700 dark:text-red-300">{{ $errorMessage }}</p>
                    </div>
                    <flux:button wire:click="retryClassification" size="sm" variant="outline" :disabled="$processing"
                        class="shrink-0">
                        @if ($processing)
                            <flux:icon.arrow-path variant="micro" class="animate-spin" />
                            Retrying...
                        @else
                            Retry classification
                        @endif
                    </flux:button>
                </div>
            </div>
        @elseif ($errorMessage !== '')
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20" role="alert">
                <p class="text-sm text-red-700 dark:text-red-300">{{ $errorMessage }}</p>
            </div>
        @endif
    </flux:card>

    {{-- Classification results --}}
    @if ($ticket !== null)
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

        <flux:card class="space-y-4" data-test="classification-result">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <flux:heading size="lg">Classification Result</flux:heading>
                <flux:badge>Ticket #{{ $ticket->id }}</flux:badge>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <flux:badge :color="$categoryColor">{{ ucfirst($ticket->ticket_category) }}</flux:badge>
                <flux:badge :color="$urgencyColor">{{ ucfirst($ticket->urgency_level) }} urgency</flux:badge>
                <flux:badge :color="$ticket->isClosed() ? null : 'green'">{{ ucfirst($ticket->status) }}</flux:badge>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Customer</flux:text>
                    <p class="font-medium">{{ $ticket->customer_name ?: 'Not provided' }}</p>
                </div>
                <div>
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Company</flux:text>
                    <p class="font-medium">{{ $ticket->company_name ?: 'Not provided' }}</p>
                </div>
                <div class="sm:col-span-2">
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Subject</flux:text>
                    <p class="font-medium">{{ $ticket->ticket_subject }}</p>
                </div>
            </div>

            <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800/50">
                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">AI summary</flux:text>
                <p class="mt-1 text-sm">{{ $ticket->ai_summary }}</p>
            </div>

            <div class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                <flux:icon.clock variant="micro" class="size-4" />
                Classified {{ $ticket->classified_at?->diffForHumans() ?? '—' }}
            </div>

            <div class="flex flex-wrap gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <flux:button wire:click="viewDetails" variant="outline">
                    View ticket details
                </flux:button>
            </div>
        </flux:card>
    @endif
</div>
