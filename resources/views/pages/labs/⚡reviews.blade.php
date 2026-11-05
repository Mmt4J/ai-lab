<?php

use App\Exceptions\ReviewSentimentException;
use App\Models\CustomerReview;
use App\Services\ReviewSentimentService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    #[Validate('nullable|string|max:255', message: 'Customer name must be 255 characters or fewer.')]
    public string $customerName = '';

    #[Validate('required|string|min:10|max:2000', message: 'A review of at least 10 characters is required.')]
    public string $reviewText = '';

    /** Whether the save-and-tag request itself is in flight. */
    public bool $processing = false;

    /** The review whose sentiment is currently being retried. */
    public ?int $retryingReviewId = null;

    /** User-facing failure message for the last tagging attempt. */
    public string $errorMessage = '';

    /**
     * Sentiment counts shown at the top of the page.
     *
     * @return array{total: int, positive: int, neutral: int, negative: int, unclassified: int}
     */
    #[Computed]
    public function stats(): array
    {
        $row = CustomerReview::query()
            ->selectRaw('
                count(*) as total_count,
                sum(case when sentiment = ? then 1 else 0 end) as positive_count,
                sum(case when sentiment = ? then 1 else 0 end) as neutral_count,
                sum(case when sentiment = ? then 1 else 0 end) as negative_count,
                sum(case when classified_at is null then 1 else 0 end) as unclassified_count
            ', ['positive', 'neutral', 'negative'])
            ->toBase()
            ->first();

        return [
            'total' => (int) ($row->total_count ?? 0),
            'positive' => (int) ($row->positive_count ?? 0),
            'neutral' => (int) ($row->neutral_count ?? 0),
            'negative' => (int) ($row->negative_count ?? 0),
            'unclassified' => (int) ($row->unclassified_count ?? 0),
        ];
    }

    /**
     * The paginated review list shown below the form.
     */
    #[Computed]
    public function reviews()
    {
        return CustomerReview::query()->latest()->paginate(10);
    }

    /**
     * Save a review, then ask the AI to tag its sentiment.
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
            $review = CustomerReview::create([
                'customer_name' => $this->customerName !== '' ? trim($this->customerName) : null,
                'review_text' => trim($this->reviewText),
            ]);
        } catch (Throwable $e) {
            report($e);

            $this->processing = false;
            $this->errorMessage = 'The review could not be saved due to a database error. Please try again.';

            $this->dispatch('toast', heading: 'Review not saved', text: $this->errorMessage, variant: 'danger');

            return;
        }

        $this->tagReview($review);

        // The review is saved whether tagging succeeded or failed, so clear
        // the form to prevent the same review being submitted twice.
        $this->reset(['customerName', 'reviewText']);
        $this->resetValidation();
        $this->resetPage();
        $this->processing = false;
    }

    /**
     * Retry sentiment tagging for a review whose earlier attempt failed.
     */
    public function retryTagging(int $reviewId): void
    {
        if ($this->retryingReviewId !== null) {
            return;
        }

        $review = CustomerReview::find($reviewId);

        if ($review === null) {
            $this->dispatch('toast', heading: 'Review not found', text: 'The review no longer exists.', variant: 'warning');

            return;
        }

        if ($review->isClassified()) {
            $this->dispatch('toast', heading: 'Already tagged', text: "Review #{$reviewId} has already been tagged.", variant: 'info');

            return;
        }

        $this->retryingReviewId = $reviewId;
        $this->errorMessage = '';

        $this->tagReview($review);

        $this->retryingReviewId = null;
    }

    /**
     * Ask the service to tag a saved review and report the outcome.
     */
    protected function tagReview(CustomerReview $review): void
    {
        try {
            app(ReviewSentimentService::class)->classify($review);

            $this->errorMessage = '';

            $this->dispatch('toast', heading: 'Review tagged', text: "Review #{$review->id} was tagged as {$review->sentiment}.", variant: 'success');
        } catch (ReviewSentimentException $e) {
            $review->markSentimentFailed($e->getMessage());

            $this->errorMessage = $e->getMessage();

            $this->dispatch('toast', heading: 'Sentiment tagging failed', text: $e->getMessage(), variant: 'danger');
        }
    }
};
?>

<div class="space-y-6">
    <div class="space-y-1">
        <flux:heading size="xl">Review Sentiment Tagger</flux:heading>
        <flux:text class="text-sm text-muted">
            Add a customer review and the AI will label it positive, neutral, or negative. The counts update as reviews
            are tagged, giving a quick snapshot of customer sentiment.
        </flux:text>
    </div>

    {{-- Sentiment counts --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-5" data-test="review-stats">
        <flux:card class="p-4">
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Total reviews</flux:text>
            <p class="mt-1 text-2xl font-semibold" data-test="stat-total">{{ $this->stats['total'] }}</p>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Positive</flux:text>
            <p class="mt-1 text-2xl font-semibold text-green-600 dark:text-green-400" data-test="stat-positive">
                {{ $this->stats['positive'] }}
            </p>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Neutral</flux:text>
            <p class="mt-1 text-2xl font-semibold text-zinc-600 dark:text-zinc-300" data-test="stat-neutral">
                {{ $this->stats['neutral'] }}
            </p>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Negative</flux:text>
            <p class="mt-1 text-2xl font-semibold text-red-600 dark:text-red-400" data-test="stat-negative">
                {{ $this->stats['negative'] }}
            </p>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Untagged</flux:text>
            <p class="mt-1 text-2xl font-semibold text-amber-600 dark:text-amber-400" data-test="stat-unclassified">
                {{ $this->stats['unclassified'] }}
            </p>
        </flux:card>
    </div>

    {{-- Add a review --}}
    <flux:card class="space-y-4">
        <div class="space-y-1">
            <flux:heading size="lg">Add a customer review</flux:heading>
            <flux:text class="text-sm text-muted">
                The review is saved first, then sent to the AI for sentiment tagging.
            </flux:text>
        </div>

        <form wire:submit="submit" class="space-y-4" novalidate>
            <flux:input wire:model="customerName" label="Customer name" placeholder="Optional" autocomplete="name"
                maxlength="255" />

            <flux:textarea wire:model="reviewText" label="Review" rows="4" maxlength="2000" required
                placeholder="e.g. The delivery was quick and the product quality exceeded my expectations." />

            <div class="flex flex-wrap gap-3">
                <flux:button type="submit" variant="filled" color="blue" :disabled="$processing">
                    @if ($processing)
                        <flux:icon.arrow-path variant="micro" class="animate-spin" />
                        Tagging review...
                    @else
                        Add &amp; Tag Review
                    @endif
                </flux:button>
            </div>
        </form>

        @if ($errorMessage !== '')
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20"
                role="alert">
                <div class="flex items-start gap-3">
                    <flux:icon.exclamation-triangle class="mt-0.5 size-5 shrink-0 text-red-500" />
                    <p class="text-sm text-red-700 dark:text-red-300">{{ $errorMessage }}</p>
                </div>
            </div>
        @endif
    </flux:card>

    {{-- Review list --}}
    <flux:card>
        @if ($this->reviews->count() === 0)
            <div class="flex flex-col items-center justify-center gap-3 py-12 text-center">
                <flux:icon.star class="size-10 text-zinc-400" />
                <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">No reviews yet.</p>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    Add your first customer review above to start tracking sentiment.
                </flux:text>
            </div>
        @else
            <flux:table :paginate="$this->reviews">
                <flux:table.columns>
                    <flux:table.column>Customer</flux:table.column>
                    <flux:table.column>Review</flux:table.column>
                    <flux:table.column>Sentiment</flux:table.column>
                    <flux:table.column>Added</flux:table.column>
                    <flux:table.column align="end">Actions</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->reviews as $review)
                        @php
                            $sentimentColor = match ($review->sentiment) {
                                'positive' => 'green',
                                'negative' => 'red',
                                default => null,
                            };
                        @endphp

                        <flux:table.row :key="$review->id">
                            <flux:table.cell variant="strong">
                                {{ $review->customer_name ?: 'Anonymous' }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <span class="block max-w-md whitespace-pre-line line-clamp-3"
                                    title="{{ $review->review_text }}">{{ $review->review_text }}</span>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($review->isClassified())
                                    <flux:badge :color="$sentimentColor">{{ ucfirst($review->sentiment) }}</flux:badge>
                                @elseif ($review->hasSentimentFailed())
                                    <flux:badge color="red" title="{{ $review->sentiment_error }}">Failed</flux:badge>
                                @else
                                    <flux:badge color="amber">Untagged</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $review->created_at->format('M j, Y') }}</flux:table.cell>
                            <flux:table.cell align="end">
                                @unless ($review->isClassified())
                                    <flux:button wire:click="retryTagging({{ $review->id }})" size="sm" variant="outline"
                                        icon="arrow-path" :disabled="$retryingReviewId !== null">
                                        @if ($retryingReviewId === $review->id)
                                            Tagging...
                                        @else
                                            Retry
                                        @endif
                                    </flux:button>
                                @endunless
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>
</div>
