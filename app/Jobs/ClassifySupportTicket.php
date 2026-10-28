<?php

namespace App\Jobs;

use App\Exceptions\SupportTicketClassificationException;
use App\Models\SupportTicketClassifier;
use App\Services\SupportTicketClassificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Classifies a saved support ticket off the request cycle.
 *
 * The ticket is always persisted before this job runs, so a failure never
 * loses the customer's message: the ticket stays open and unclassified and
 * the failure reason is recorded for the UI to offer a retry.
 */
class ClassifySupportTicket implements ShouldQueue
{
    use Queueable;

    /**
     * Domain failures are surfaced to the user for a manual retry rather
     * than being retried indefinitely by the queue worker.
     */
    public int $tries = 1;

    public function __construct(public int $ticketId) {}

    public function handle(SupportTicketClassificationService $service): void
    {
        $ticket = SupportTicketClassifier::withTrashed()->find($this->ticketId);

        // The ticket was removed or already classified while queued.
        if ($ticket === null || $ticket->isClassified()) {
            return;
        }

        // A ticket trashed before it could be classified is left unclassified;
        // clear the pending marker so it does not appear stuck in progress.
        if ($ticket->trashed()) {
            $ticket->clearClassificationRequest();

            return;
        }

        try {
            $service->classify($ticket);
        } catch (SupportTicketClassificationException $e) {
            $ticket->markClassificationFailed($e->getMessage());
        }
    }
}
