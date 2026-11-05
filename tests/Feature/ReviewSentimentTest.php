<?php

use App\Ai\Agents\ReviewSentimentTagger;
use App\Models\CustomerReview;
use App\Models\User;
use Laravel\Ai\Exceptions\RateLimitedException;
use Livewire\Livewire;

function validReviewInput(): array
{
    return [
        'customerName' => 'Ada Lovelace',
        'reviewText' => 'The delivery was incredibly fast and the product quality exceeded my expectations.',
    ];
}

function reviewStatFromHtml(string $html, string $key): int
{
    preg_match('/data-test="stat-'.$key.'"[^>]*>\s*(\d+)/', $html, $matches);

    return (int) ($matches[1] ?? -1);
}

test('the reviews page requires authentication', function () {
    $this->get(route('labs.reviews'))->assertRedirect(route('login'));
});

test('authenticated users can view the reviews page', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('labs.reviews'))
        ->assertOk()
        ->assertSee('Review Sentiment Tagger')
        ->assertSee('Add a customer review');
});

test('a review is required before sentiment tagging', function () {
    ReviewSentimentTagger::fake()->preventStrayPrompts();

    Livewire::actingAs(User::factory()->create())
        ->test('pages::labs.reviews')
        ->set('reviewText', '')
        ->call('submit')
        ->assertHasErrors(['reviewText' => 'required']);

    expect(CustomerReview::count())->toBe(0);

    ReviewSentimentTagger::assertNeverPrompted();
});

test('a review must be long enough to tag', function () {
    ReviewSentimentTagger::fake()->preventStrayPrompts();

    Livewire::actingAs(User::factory()->create())
        ->test('pages::labs.reviews')
        ->set(validReviewInput())
        ->set('reviewText', 'too short')
        ->call('submit')
        ->assertHasErrors(['reviewText' => 'min']);

    expect(CustomerReview::count())->toBe(0);

    ReviewSentimentTagger::assertNeverPrompted();
});

test('a submitted review is saved and tagged by the ai', function () {
    ReviewSentimentTagger::fake([[
        'sentiment' => 'positive',
    ]]);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::labs.reviews')
        ->set(validReviewInput())
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('processing', false)
        ->assertSet('customerName', '')
        ->assertSet('reviewText', '')
        ->assertSee('Positive')
        ->assertSee('Ada Lovelace');

    $review = CustomerReview::sole();

    expect($review->customer_name)->toBe('Ada Lovelace')
        ->and($review->review_text)->toBe('The delivery was incredibly fast and the product quality exceeded my expectations.')
        ->and($review->sentiment)->toBe('positive')
        ->and($review->classified_at)->not->toBeNull()
        ->and($review->sentiment_error)->toBeNull();
});

test('the customer name is optional', function () {
    ReviewSentimentTagger::fake([[
        'sentiment' => 'neutral',
    ]]);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::labs.reviews')
        ->set(validReviewInput())
        ->set('customerName', '')
        ->call('submit')
        ->assertHasNoErrors();

    $review = CustomerReview::sole();

    expect($review->customer_name)->toBeNull()
        ->and($review->sentiment)->toBe('neutral');
});

test('sentiment prompts fence the review content as untrusted data', function () {
    ReviewSentimentTagger::fake([[
        'sentiment' => 'negative',
    ]]);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::labs.reviews')
        ->set(validReviewInput())
        ->call('submit');

    ReviewSentimentTagger::assertPrompted(function ($prompt): bool {
        return str_contains($prompt->prompt, '<review>')
            && str_contains($prompt->prompt, 'Ada Lovelace')
            && str_contains($prompt->prompt, 'The delivery was incredibly fast');
    });
});

test('an unsupported sentiment leaves the review saved and unclassified', function () {
    ReviewSentimentTagger::fake([[
        'sentiment' => 'mixed',
    ]]);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::labs.reviews')
        ->set(validReviewInput())
        ->call('submit')
        ->assertSet('errorMessage', fn (string $message): bool => str_contains($message, 'unsupported sentiment'))
        ->assertSee('Retry');

    $review = CustomerReview::sole();

    expect($review->sentiment)->toBeNull()
        ->and($review->classified_at)->toBeNull()
        ->and($review->sentiment_error)->not->toBeNull()
        ->and($review->review_text)->toBe('The delivery was incredibly fast and the product quality exceeded my expectations.');
});

test('a rate limited ai provider keeps the review saved and offers a retry', function () {
    ReviewSentimentTagger::fake(fn () => throw RateLimitedException::forProvider('groq'));

    Livewire::actingAs(User::factory()->create())
        ->test('pages::labs.reviews')
        ->set(validReviewInput())
        ->call('submit')
        ->assertSee('rate limited')
        ->assertSee('Retry');

    $review = CustomerReview::sole();

    expect($review->sentiment)->toBeNull()
        ->and($review->classified_at)->toBeNull()
        ->and($review->hasSentimentFailed())->toBeTrue();
});

test('retrying tagging classifies the existing review without creating another', function () {
    ReviewSentimentTagger::fake(fn () => throw RateLimitedException::forProvider('groq'));

    $component = Livewire::actingAs(User::factory()->create())
        ->test('pages::labs.reviews')
        ->set(validReviewInput())
        ->call('submit');

    $review = CustomerReview::sole();

    ReviewSentimentTagger::fake([[
        'sentiment' => 'negative',
    ]]);

    $component->call('retryTagging', $review->id)
        ->assertSet('retryingReviewId', null)
        ->assertSet('errorMessage', '');

    expect(CustomerReview::count())->toBe(1);

    $review->refresh();

    expect($review->sentiment)->toBe('negative')
        ->and($review->classified_at)->not->toBeNull()
        ->and($review->sentiment_error)->toBeNull();
});

test('the page shows sentiment counts for saved reviews', function () {
    CustomerReview::factory()->withSentiment('positive')->count(2)->create();
    CustomerReview::factory()->withSentiment('neutral')->create();
    CustomerReview::factory()->withSentiment('negative')->create();
    CustomerReview::factory()->unclassified()->create();

    $html = Livewire::actingAs(User::factory()->create())
        ->test('pages::labs.reviews')
        ->html();

    expect(reviewStatFromHtml($html, 'total'))->toBe(5)
        ->and(reviewStatFromHtml($html, 'positive'))->toBe(2)
        ->and(reviewStatFromHtml($html, 'neutral'))->toBe(1)
        ->and(reviewStatFromHtml($html, 'negative'))->toBe(1)
        ->and(reviewStatFromHtml($html, 'unclassified'))->toBe(1);
});
