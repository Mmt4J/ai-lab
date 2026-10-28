<?php

use App\Ai\Agents\SupportTicketClassifier as TicketClassifierAgent;
use App\Jobs\ClassifySupportTicket;
use App\Models\SupportTicketClassifier;
use App\Services\SupportTicketClassificationService;
use Laravel\Ai\Exceptions\RateLimitedException;

test('the job classifies an unclassified ticket and clears any previous error', function () {
    $ticket = SupportTicketClassifier::factory()->unclassified()->create([
        'classification_requested_at' => now(),
        'classification_error' => 'A previous attempt failed.',
    ]);

    TicketClassifierAgent::fake([[
        'ticket_category' => 'technical',
        'urgency_level' => 'medium',
        'ai_summary' => 'Customer cannot log in to their account.',
    ]]);

    (new ClassifySupportTicket($ticket->id))->handle(app(SupportTicketClassificationService::class));

    $ticket->refresh();

    expect($ticket->ticket_category)->toBe('technical')
        ->and($ticket->urgency_level)->toBe('medium')
        ->and($ticket->classification_error)->toBeNull()
        ->and($ticket->isClassified())->toBeTrue();
});

test('the job records a failure without throwing and keeps the original ticket', function () {
    $ticket = SupportTicketClassifier::factory()->unclassified()->create([
        'ticket_message' => 'The login page keeps rejecting my password.',
        'classification_requested_at' => now(),
    ]);

    TicketClassifierAgent::fake(fn () => throw RateLimitedException::forProvider('groq'));

    (new ClassifySupportTicket($ticket->id))->handle(app(SupportTicketClassificationService::class));

    $ticket->refresh();

    expect($ticket->classified_at)->toBeNull()
        ->and($ticket->hasClassificationFailed())->toBeTrue()
        ->and($ticket->classification_error)->toContain('rate limited')
        ->and($ticket->ticket_message)->toBe('The login page keeps rejecting my password.')
        ->and($ticket->status)->toBe('open');
});

test('the job leaves an already classified ticket untouched', function () {
    $ticket = SupportTicketClassifier::factory()->create();

    TicketClassifierAgent::fake()->preventStrayPrompts();

    (new ClassifySupportTicket($ticket->id))->handle(app(SupportTicketClassificationService::class));

    expect($ticket->refresh()->isClassified())->toBeTrue();

    TicketClassifierAgent::assertNeverPrompted();
});

test('the job does nothing when the ticket no longer exists', function () {
    TicketClassifierAgent::fake()->preventStrayPrompts();

    (new ClassifySupportTicket(999999))->handle(app(SupportTicketClassificationService::class));

    TicketClassifierAgent::assertNeverPrompted();
});

test('the job leaves a trashed ticket unclassified and clears its pending state', function () {
    $ticket = SupportTicketClassifier::factory()->pending()->create();
    $ticket->delete();

    TicketClassifierAgent::fake()->preventStrayPrompts();

    (new ClassifySupportTicket($ticket->id))->handle(app(SupportTicketClassificationService::class));

    $fresh = SupportTicketClassifier::withTrashed()->find($ticket->id);

    expect($fresh->isClassificationPending())->toBeFalse()
        ->and($fresh->trashed())->toBeTrue()
        ->and($fresh->classified_at)->toBeNull();

    TicketClassifierAgent::assertNeverPrompted();
});
