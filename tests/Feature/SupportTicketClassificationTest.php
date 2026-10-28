<?php

use App\Ai\Agents\SupportTicketClassifier as TicketClassifierAgent;
use App\Jobs\ClassifySupportTicket;
use App\Models\SupportTicketClassifier;
use App\Models\User;
use App\Services\SupportTicketClassificationService;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Livewire\Livewire;

function validTicketInput(): array
{
    return [
        'customerName' => 'Ada Lovelace',
        'companyName' => 'Analytical Engines Ltd',
        'email' => 'ada@example.com',
        'ticketSubject' => 'Duplicate subscription charge',
        'ticketMessage' => 'I have been charged twice for my subscription. Please refund the extra payment.',
    ];
}

test('the tickets page requires authentication', function () {
    $this->get(route('labs.tickets'))->assertRedirect(route('login'));
});

test('authenticated users can view the tickets page', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('labs.tickets'))->assertOk();
});

test('submitting a ticket requires a subject and a message', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('ticket-classifier')
        ->set('ticketSubject', '')
        ->set('ticketMessage', '')
        ->call('submit')
        ->assertHasErrors(['ticketSubject' => 'required', 'ticketMessage' => 'required']);

    expect(SupportTicketClassifier::count())->toBe(0);
});

test('submitting a ticket requires a meaningful message length', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('ticket-classifier')
        ->set(validTicketInput())
        ->set('ticketMessage', 'too short')
        ->call('submit')
        ->assertHasErrors(['ticketMessage' => 'min']);

    expect(SupportTicketClassifier::count())->toBe(0);
});

test('submitting a ticket rejects an invalid email address', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('ticket-classifier')
        ->set(validTicketInput())
        ->set('email', 'not-an-email')
        ->call('submit')
        ->assertHasErrors(['email' => 'email']);

    expect(SupportTicketClassifier::count())->toBe(0);
});

test('a submitted ticket is saved and classified by the ai', function () {
    TicketClassifierAgent::fake([[
        'ticket_category' => 'billing',
        'urgency_level' => 'high',
        'ai_summary' => 'Customer reports a duplicate subscription charge and requests a refund.',
    ]]);

    $component = Livewire::actingAs(User::factory()->create())
        ->test('ticket-classifier')
        ->set(validTicketInput())
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('processing', false)
        ->assertDispatched('tickets-updated');

    $ticket = SupportTicketClassifier::sole();

    expect($ticket->customer_name)->toBe('Ada Lovelace')
        ->and($ticket->company_name)->toBe('Analytical Engines Ltd')
        ->and($ticket->email)->toBe('ada@example.com')
        ->and($ticket->ticket_subject)->toBe('Duplicate subscription charge')
        ->and($ticket->ticket_category)->toBe('billing')
        ->and($ticket->urgency_level)->toBe('high')
        ->and($ticket->ai_summary)->toContain('duplicate subscription charge')
        ->and($ticket->status)->toBe('open')
        ->and($ticket->classified_at)->not->toBeNull();

    $component->assertSee('Billing')
        ->assertSee('High urgency')
        ->assertSee('Customer reports a duplicate subscription charge')
        ->assertSee('#'.$ticket->id);
});

test('customer name company and email are optional', function () {
    TicketClassifierAgent::fake([[
        'ticket_category' => 'technical',
        'urgency_level' => 'low',
        'ai_summary' => 'Customer reports a login problem.',
    ]]);

    Livewire::actingAs(User::factory()->create())
        ->test('ticket-classifier')
        ->set(validTicketInput())
        ->set('customerName', '')
        ->set('companyName', '')
        ->set('email', '')
        ->call('submit')
        ->assertHasNoErrors();

    $ticket = SupportTicketClassifier::sole();

    expect($ticket->customer_name)->toBeNull()
        ->and($ticket->company_name)->toBeNull()
        ->and($ticket->email)->toBeNull()
        ->and($ticket->ticket_category)->toBe('technical');
});

test('classification prompts fence the ticket content as untrusted data', function () {
    TicketClassifierAgent::fake([[
        'ticket_category' => 'billing',
        'urgency_level' => 'high',
        'ai_summary' => 'Customer requests a refund for a duplicate charge.',
    ]]);

    Livewire::actingAs(User::factory()->create())
        ->test('ticket-classifier')
        ->set(validTicketInput())
        ->call('submit');

    TicketClassifierAgent::assertPrompted(function ($prompt): bool {
        return str_contains($prompt->prompt, '<ticket>')
            && str_contains($prompt->prompt, 'Duplicate subscription charge')
            && str_contains($prompt->prompt, 'Please refund the extra payment');
    });
});

test('an ai response with an unsupported category leaves the ticket saved and unclassified', function () {
    TicketClassifierAgent::fake([[
        'ticket_category' => 'sales',
        'urgency_level' => 'high',
        'ai_summary' => 'Something happened.',
    ]]);

    Livewire::actingAs(User::factory()->create())
        ->test('ticket-classifier')
        ->set(validTicketInput())
        ->call('submit')
        ->assertSet('ticket', null)
        ->assertSet('failedTicketId', fn ($id) => $id !== null)
        ->assertSee('classification failed');

    $ticket = SupportTicketClassifier::sole();

    expect($ticket->ticket_category)->toBeNull()
        ->and($ticket->urgency_level)->toBeNull()
        ->and($ticket->ai_summary)->toBeNull()
        ->and($ticket->classified_at)->toBeNull()
        ->and($ticket->status)->toBe('open')
        ->and($ticket->ticket_message)->toBe('I have been charged twice for my subscription. Please refund the extra payment.');
});

test('an ai response with an unsupported urgency level leaves the ticket unclassified', function () {
    TicketClassifierAgent::fake([[
        'ticket_category' => 'billing',
        'urgency_level' => 'urgent',
        'ai_summary' => 'Something happened.',
    ]]);

    Livewire::actingAs(User::factory()->create())
        ->test('ticket-classifier')
        ->set(validTicketInput())
        ->call('submit')
        ->assertSet('ticket', null);

    $ticket = SupportTicketClassifier::sole();

    expect($ticket->urgency_level)->toBeNull()
        ->and($ticket->classified_at)->toBeNull();
});

test('an ai response with an empty summary leaves the ticket unclassified', function () {
    TicketClassifierAgent::fake([[
        'ticket_category' => 'billing',
        'urgency_level' => 'high',
        'ai_summary' => '   ',
    ]]);

    Livewire::actingAs(User::factory()->create())
        ->test('ticket-classifier')
        ->set(validTicketInput())
        ->call('submit')
        ->assertSet('ticket', null);

    $ticket = SupportTicketClassifier::sole();

    expect($ticket->ai_summary)->toBeNull()
        ->and($ticket->classified_at)->toBeNull();
});

test('a rate limited ai provider keeps the ticket saved and offers a retry', function () {
    TicketClassifierAgent::fake(fn () => throw RateLimitedException::forProvider('groq'));

    Livewire::actingAs(User::factory()->create())
        ->test('ticket-classifier')
        ->set(validTicketInput())
        ->call('submit')
        ->assertSet('ticket', null)
        ->assertSee('rate limited');

    $ticket = SupportTicketClassifier::sole();

    expect($ticket->status)->toBe('open')
        ->and($ticket->classified_at)->toBeNull()
        ->and($ticket->ticket_subject)->toBe('Duplicate subscription charge');
});

test('a provider connection failure keeps the ticket saved and offers a retry', function () {
    TicketClassifierAgent::fake(fn () => throw ProviderConnectionException::forProvider('groq'));

    Livewire::actingAs(User::factory()->create())
        ->test('ticket-classifier')
        ->set(validTicketInput())
        ->call('submit')
        ->assertSet('ticket', null);

    expect(SupportTicketClassifier::count())->toBe(1);
});

test('retrying classification classifies the existing ticket without creating another', function () {
    TicketClassifierAgent::fake(fn () => throw RateLimitedException::forProvider('groq'));

    $component = Livewire::actingAs(User::factory()->create())
        ->test('ticket-classifier')
        ->set(validTicketInput())
        ->call('submit');

    $ticket = SupportTicketClassifier::sole();

    TicketClassifierAgent::fake([[
        'ticket_category' => 'billing',
        'urgency_level' => 'high',
        'ai_summary' => 'Customer reports a duplicate subscription charge and requests a refund.',
    ]]);

    $component->call('retryClassification')
        ->assertSet('failedTicketId', null)
        ->assertSet('processing', false);

    expect(SupportTicketClassifier::count())->toBe(1);

    $ticket->refresh();

    expect($ticket->ticket_category)->toBe('billing')
        ->and($ticket->urgency_level)->toBe('high')
        ->and($ticket->classified_at)->not->toBeNull();
});

test('retrying classification reuses the saved ticket subject and message', function () {
    TicketClassifierAgent::fake(fn () => throw RateLimitedException::forProvider('groq'));

    $component = Livewire::actingAs(User::factory()->create())
        ->test('ticket-classifier')
        ->set(validTicketInput())
        ->call('submit');

    TicketClassifierAgent::fake([[
        'ticket_category' => 'billing',
        'urgency_level' => 'medium',
        'ai_summary' => 'Duplicate charge reported.',
    ]]);

    $component->call('retryClassification');

    TicketClassifierAgent::assertPrompted(function ($prompt): bool {
        return str_contains($prompt->prompt, 'Duplicate subscription charge')
            && str_contains($prompt->prompt, 'Please refund the extra payment');
    });
});

test('a failed retry leaves the original ticket message intact', function () {
    TicketClassifierAgent::fake(fn () => throw RateLimitedException::forProvider('groq'));

    $component = Livewire::actingAs(User::factory()->create())
        ->test('ticket-classifier')
        ->set(validTicketInput())
        ->call('submit');

    TicketClassifierAgent::fake(fn () => throw ProviderConnectionException::forProvider('groq'));

    $component->call('retryClassification')
        ->assertSet('ticket', null)
        ->assertSet('failedTicketId', fn ($id) => $id !== null);

    $ticket = SupportTicketClassifier::sole();

    expect($ticket->ticket_message)->toBe('I have been charged twice for my subscription. Please refund the extra payment.')
        ->and($ticket->classified_at)->toBeNull()
        ->and($ticket->status)->toBe('open');
});

test('classifying another ticket resets the form and results', function () {
    TicketClassifierAgent::fake([[
        'ticket_category' => 'complaint',
        'urgency_level' => 'low',
        'ai_summary' => 'Customer is unhappy with the service.',
    ]]);

    Livewire::actingAs(User::factory()->create())
        ->test('ticket-classifier')
        ->set(validTicketInput())
        ->call('submit')
        ->assertSet('ticket', fn ($ticket) => $ticket !== null)
        ->call('classifyAnother')
        ->assertSet('ticket', null)
        ->assertSet('ticketSubject', '')
        ->assertSet('ticketMessage', '')
        ->assertHasNoErrors();
});

test('submitting a ticket queues the classification instead of calling the ai inline', function () {
    Queue::fake();

    Livewire::actingAs(User::factory()->create())
        ->test('ticket-classifier')
        ->set(validTicketInput())
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('pendingTicketId', fn ($id) => $id !== null)
        ->assertSet('ticket', null)
        ->assertSee('Classifying ticket');

    $ticket = SupportTicketClassifier::sole();

    Queue::assertPushed(ClassifySupportTicket::class, fn (ClassifySupportTicket $job) => $job->ticketId === $ticket->id);

    expect($ticket->isClassificationPending())->toBeTrue();

    TicketClassifierAgent::assertNeverPrompted();
});

test('the classifier resolves a pending ticket once the queued job completes', function () {
    Queue::fake();

    $component = Livewire::actingAs(User::factory()->create())
        ->test('ticket-classifier')
        ->set(validTicketInput())
        ->call('submit')
        ->assertSet('pendingTicketId', fn ($id) => $id !== null);

    $ticket = SupportTicketClassifier::sole();

    TicketClassifierAgent::fake([[
        'ticket_category' => 'billing',
        'urgency_level' => 'high',
        'ai_summary' => 'Customer reports a duplicate subscription charge.',
    ]]);

    (new ClassifySupportTicket($ticket->id))->handle(app(SupportTicketClassificationService::class));

    $component->call('refreshPending')
        ->assertSet('pendingTicketId', null)
        ->assertSet('failedTicketId', null)
        ->assertSet('ticket', fn ($ticket) => $ticket !== null)
        ->assertSee('Billing');
});
