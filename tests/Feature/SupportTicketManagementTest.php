<?php

use App\Ai\Agents\SupportTicketClassifier as TicketClassifierAgent;
use App\Models\SupportTicketClassifier;
use App\Models\User;
use Laravel\Ai\Exceptions\RateLimitedException;
use Livewire\Livewire;

test('the dashboard shows database backed statistics', function () {
    SupportTicketClassifier::factory()->count(3)->create(['urgency_level' => 'high', 'ticket_category' => 'complaint']);
    SupportTicketClassifier::factory()->count(2)->create(['urgency_level' => 'low', 'ticket_category' => 'complaint', 'status' => 'closed', 'closed_at' => now()]);
    SupportTicketClassifier::factory()->count(4)->create(['urgency_level' => 'low', 'ticket_category' => 'billing']);
    SupportTicketClassifier::factory()->count(2)->create(['urgency_level' => 'low', 'ticket_category' => 'technical']);
    SupportTicketClassifier::factory()->count(1)->create(['urgency_level' => 'low', 'ticket_category' => 'complaint']);

    Livewire::actingAs(User::factory()->create())
        ->test('ticket-dashboard')
        ->assertSet('stats.total', 12)
        ->assertSet('stats.open', 10)
        ->assertSet('stats.closed', 2)
        ->assertSet('stats.high_urgency', 3)
        ->assertSet('stats.billing', 4)
        ->assertSet('stats.technical', 2)
        ->assertSet('stats.complaint', 6)
        ->assertSet('stats.trashed', 0);
});

test('search matches customer name company email subject and message', function () {
    SupportTicketClassifier::factory()->create(['customer_name' => 'Grace Hopper', 'ticket_subject' => 'Compiler bug', 'ticket_message' => 'Unrelated content']);
    SupportTicketClassifier::factory()->create(['company_name' => 'Navy Systems', 'ticket_subject' => 'Compiler bug', 'ticket_message' => 'Unrelated content']);
    SupportTicketClassifier::factory()->create(['email' => 'found@example.com', 'ticket_subject' => 'Compiler bug', 'ticket_message' => 'Unrelated content']);
    SupportTicketClassifier::factory()->create(['ticket_subject' => 'Unique subject phrase', 'ticket_message' => 'Unrelated content']);
    SupportTicketClassifier::factory()->create(['ticket_subject' => 'Compiler bug', 'ticket_message' => 'A unique message phrase appears here']);
    SupportTicketClassifier::factory()->create(['ticket_subject' => 'Compiler bug', 'ticket_message' => 'Unrelated content']);

    foreach (['Grace Hopper', 'Navy Systems', 'found@example.com', 'Unique subject phrase', 'A unique message phrase'] as $term) {
        Livewire::actingAs(User::factory()->create())
            ->test('ticket-dashboard')
            ->set('search', $term)
            ->assertSet('tickets', fn ($tickets) => $tickets->total() === 1);
    }
});

test('filters combine with each other and with search', function () {
    SupportTicketClassifier::factory()->create([
        'ticket_category' => 'billing',
        'urgency_level' => 'high',
        'status' => 'open',
        'ticket_subject' => 'Refund request for invoice 123',
    ]);
    SupportTicketClassifier::factory()->create([
        'ticket_category' => 'billing',
        'urgency_level' => 'low',
        'status' => 'open',
        'ticket_subject' => 'Refund question',
    ]);
    SupportTicketClassifier::factory()->create([
        'ticket_category' => 'billing',
        'urgency_level' => 'high',
        'status' => 'closed',
        'closed_at' => now(),
        'ticket_subject' => 'Refund request for invoice 999',
    ]);
    SupportTicketClassifier::factory()->create([
        'ticket_category' => 'technical',
        'urgency_level' => 'high',
        'status' => 'open',
        'ticket_subject' => 'Refund of my patience',
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test('ticket-dashboard')
        ->set('categoryFilter', 'billing')
        ->set('urgencyFilter', 'high')
        ->set('statusFilter', 'open')
        ->set('search', 'Refund')
        ->assertSet('tickets', fn ($tickets) => $tickets->total() === 1)
        ->assertSee('Refund request for invoice 123')
        ->assertDontSee('Refund question')
        ->assertDontSee('invoice 999')
        ->assertDontSee('Refund of my patience');
});

test('tickets are paginated and changing filters resets the page', function () {
    SupportTicketClassifier::factory()->count(15)->create();

    $component = Livewire::actingAs(User::factory()->create())
        ->test('ticket-dashboard')
        ->assertSet('tickets', fn ($tickets) => $tickets->count() === 10)
        ->call('gotoPage', 2)
        ->assertSet('paginators.page', 2)
        ->assertSet('tickets', fn ($tickets) => $tickets->count() === 5);

    $component->set('categoryFilter', 'billing')
        ->assertSet('paginators.page', 1);

    $component->set('search', 'no match')
        ->assertSet('paginators.page', 1)
        ->assertSet('tickets', fn ($tickets) => $tickets->total() === 0);
});

test('clearing filters removes all filter state', function () {
    SupportTicketClassifier::factory()->create(['ticket_category' => 'billing']);

    Livewire::actingAs(User::factory()->create())
        ->test('ticket-dashboard')
        ->set('search', 'something')
        ->set('categoryFilter', 'technical')
        ->set('urgencyFilter', 'high')
        ->set('statusFilter', 'closed')
        ->call('clearFilters')
        ->assertSet('search', '')
        ->assertSet('categoryFilter', '')
        ->assertSet('urgencyFilter', '')
        ->assertSet('statusFilter', '')
        ->assertSet('tickets', fn ($tickets) => $tickets->total() === 1);
});

test('closing a ticket sets the closed status and timestamp', function () {
    $ticket = SupportTicketClassifier::factory()->create(['status' => 'open', 'closed_at' => null]);

    Livewire::actingAs(User::factory()->create())
        ->test('ticket-dashboard')
        ->call('requestStatusChange', $ticket->id)
        ->assertSet('pendingStatusChange.action', 'close')
        ->call('confirmStatusChange')
        ->assertSet('pendingStatusChange', null)
        ->assertDispatched('toast');

    $ticket->refresh();

    expect($ticket->status)->toBe('closed')
        ->and($ticket->closed_at)->not->toBeNull();
});

test('reopening a ticket clears the closed timestamp', function () {
    $ticket = SupportTicketClassifier::factory()->closed()->create();

    Livewire::actingAs(User::factory()->create())
        ->test('ticket-dashboard')
        ->call('requestStatusChange', $ticket->id)
        ->assertSet('pendingStatusChange.action', 'reopen')
        ->call('confirmStatusChange')
        ->assertDispatched('toast');

    $ticket->refresh();

    expect($ticket->status)->toBe('open')
        ->and($ticket->closed_at)->toBeNull();
});

test('closing an already closed ticket is prevented', function () {
    $ticket = SupportTicketClassifier::factory()->closed()->create();
    $closedAt = $ticket->closed_at;

    Livewire::actingAs(User::factory()->create())
        ->test('ticket-dashboard')
        ->set('pendingStatusChange', ['id' => $ticket->id, 'action' => 'close'])
        ->call('confirmStatusChange')
        ->assertDispatched('toast');

    $ticket->refresh();

    expect($ticket->status)->toBe('closed')
        ->and($ticket->closed_at->equalTo($closedAt))->toBeTrue();
});

test('reopening an open ticket is prevented', function () {
    $ticket = SupportTicketClassifier::factory()->create(['status' => 'open', 'closed_at' => null]);

    Livewire::actingAs(User::factory()->create())
        ->test('ticket-dashboard')
        ->set('pendingStatusChange', ['id' => $ticket->id, 'action' => 'reopen'])
        ->call('confirmStatusChange')
        ->assertDispatched('toast');

    $ticket->refresh();

    expect($ticket->status)->toBe('open')
        ->and($ticket->closed_at)->toBeNull();
});

test('statistics update after closing and reopening a ticket', function () {
    SupportTicketClassifier::factory()->count(2)->create(['status' => 'open', 'closed_at' => null]);
    $ticket = SupportTicketClassifier::factory()->create(['status' => 'open', 'closed_at' => null]);

    $component = Livewire::actingAs(User::factory()->create())
        ->test('ticket-dashboard')
        ->assertSet('stats.open', 3)
        ->assertSet('stats.closed', 0);

    $component->call('requestStatusChange', $ticket->id)
        ->call('confirmStatusChange')
        ->assertSet('stats.open', 2)
        ->assertSet('stats.closed', 1);

    $component->call('requestStatusChange', $ticket->id)
        ->call('confirmStatusChange')
        ->assertSet('stats.open', 3)
        ->assertSet('stats.closed', 0);
});

test('retrying classification from the dashboard updates the existing ticket', function () {
    $ticket = SupportTicketClassifier::factory()->unclassified()->create([
        'ticket_subject' => 'Cannot log in to my account',
        'ticket_message' => 'The login page keeps rejecting my password.',
    ]);

    TicketClassifierAgent::fake([[
        'ticket_category' => 'technical',
        'urgency_level' => 'medium',
        'ai_summary' => 'Customer cannot log in and the login page rejects their password.',
    ]]);

    Livewire::actingAs(User::factory()->create())
        ->test('ticket-dashboard')
        ->call('retryClassification', $ticket->id)
        ->assertDispatched('toast');

    $ticket->refresh();

    expect(SupportTicketClassifier::count())->toBe(1)
        ->and($ticket->ticket_category)->toBe('technical')
        ->and($ticket->urgency_level)->toBe('medium')
        ->and($ticket->ai_summary)->toContain('cannot log in')
        ->and($ticket->classified_at)->not->toBeNull();
});

test('a failed retry from the dashboard preserves the original ticket', function () {
    $ticket = SupportTicketClassifier::factory()->unclassified()->create([
        'ticket_message' => 'The login page keeps rejecting my password.',
    ]);

    TicketClassifierAgent::fake(fn () => throw RateLimitedException::forProvider('groq'));

    Livewire::actingAs(User::factory()->create())
        ->test('ticket-dashboard')
        ->call('retryClassification', $ticket->id)
        ->assertDispatched('toast');

    $ticket->refresh();

    expect(SupportTicketClassifier::count())->toBe(1)
        ->and($ticket->ticket_category)->toBeNull()
        ->and($ticket->classified_at)->toBeNull()
        ->and($ticket->ticket_message)->toBe('The login page keeps rejecting my password.');
});

test('retrying classification for an already classified ticket is prevented', function () {
    $ticket = SupportTicketClassifier::factory()->create();

    TicketClassifierAgent::fake()->preventStrayPrompts();

    Livewire::actingAs(User::factory()->create())
        ->test('ticket-dashboard')
        ->call('retryClassification', $ticket->id)
        ->assertDispatched('toast');

    $ticket->refresh();

    expect($ticket->classified_at)->not->toBeNull();
});

test('viewing a ticket opens its details', function () {
    $ticket = SupportTicketClassifier::factory()->create([
        'customer_name' => 'Katherine Johnson',
        'ticket_subject' => 'Flight path calculation error',
        'ticket_message' => 'The numbers do not match my manual calculations.',
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test('ticket-dashboard')
        ->call('viewTicket', $ticket->id)
        ->assertSet('viewingTicketId', $ticket->id)
        ->assertDispatched('modal-show')
        ->assertSee('Katherine Johnson')
        ->assertSee('Flight path calculation error')
        ->assertSee('The numbers do not match my manual calculations.');
});

test('the unclassified ticket list exposes a retry action', function () {
    SupportTicketClassifier::factory()->unclassified()->create();
    SupportTicketClassifier::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test('ticket-dashboard')
        ->assertSee('Retry classification')
        ->assertSee('Unclassified');
});

test('the dashboard surfaces tickets awaiting classification', function () {
    SupportTicketClassifier::factory()->pending()->create();
    SupportTicketClassifier::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test('ticket-dashboard')
        ->assertSet('stats.pending', 1)
        ->assertSee('Classifying');
});

test('search is case insensitive', function () {
    SupportTicketClassifier::factory()->create([
        'customer_name' => 'Grace Hopper',
        'company_name' => 'Navy Systems',
        'ticket_subject' => 'Compiler bug',
        'ticket_message' => 'Unrelated content',
    ]);
    SupportTicketClassifier::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test('ticket-dashboard')
        ->set('search', 'grace hopper')
        ->assertSet('tickets', fn ($tickets) => $tickets->total() === 1)
        ->set('search', 'NAVY SYSTEMS')
        ->assertSet('tickets', fn ($tickets) => $tickets->total() === 1);
});

test('a ticket can be moved to the trash and restored', function () {
    $ticket = SupportTicketClassifier::factory()->create(['status' => 'open', 'closed_at' => null]);

    $component = Livewire::actingAs(User::factory()->create())
        ->test('ticket-dashboard')
        ->call('requestDelete', $ticket->id)
        ->assertSet('pendingDeleteId', $ticket->id)
        ->call('confirmDelete')
        ->assertSet('pendingDeleteId', null)
        ->assertSet('stats.trashed', 1)
        ->assertDispatched('toast');

    $this->assertSoftDeleted('support_ticket_classifiers', ['id' => $ticket->id]);

    $component->call('restoreTicket', $ticket->id)
        ->assertSet('stats.trashed', 0)
        ->assertDispatched('toast');

    $this->assertNotSoftDeleted('support_ticket_classifiers', ['id' => $ticket->id]);
});

test('the trashed filter lists only trashed tickets', function () {
    SupportTicketClassifier::factory()->create(['ticket_subject' => 'Active ticket subject']);
    $trashed = SupportTicketClassifier::factory()->create(['ticket_subject' => 'Trashed ticket subject']);
    $trashed->delete();

    Livewire::actingAs(User::factory()->create())
        ->test('ticket-dashboard')
        ->set('statusFilter', 'trashed')
        ->assertSet('tickets', fn ($tickets) => $tickets->total() === 1)
        ->assertSee('Trashed ticket subject')
        ->assertDontSee('Active ticket subject')
        ->assertSee('Trashed');
});

test('deleting a ticket requires confirmation and can be cancelled', function () {
    $ticket = SupportTicketClassifier::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test('ticket-dashboard')
        ->call('requestDelete', $ticket->id)
        ->assertSet('pendingDeleteId', $ticket->id)
        ->call('clearPendingDelete')
        ->assertSet('pendingDeleteId', null)
        ->call('confirmDelete')
        ->assertSet('pendingDeleteId', null);

    $this->assertNotSoftDeleted('support_ticket_classifiers', ['id' => $ticket->id]);
});

test('restoring a ticket that is not in the trash is prevented', function () {
    $ticket = SupportTicketClassifier::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test('ticket-dashboard')
        ->call('restoreTicket', $ticket->id)
        ->assertDispatched('toast');

    $this->assertNotSoftDeleted('support_ticket_classifiers', ['id' => $ticket->id]);
});

test('viewing a trashed ticket still shows its details', function () {
    $ticket = SupportTicketClassifier::factory()->create([
        'customer_name' => 'Katherine Johnson',
        'ticket_subject' => 'Flight path calculation error',
        'ticket_message' => 'The numbers do not match my manual calculations.',
    ]);
    $ticket->delete();

    Livewire::actingAs(User::factory()->create())
        ->test('ticket-dashboard')
        ->set('statusFilter', 'trashed')
        ->call('viewTicket', $ticket->id)
        ->assertSet('viewingTicketId', $ticket->id)
        ->assertDispatched('modal-show')
        ->assertSee('Katherine Johnson')
        ->assertSee('Flight path calculation error');
});
