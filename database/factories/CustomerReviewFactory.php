<?php

namespace Database\Factories;

use App\Models\CustomerReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerReview>
 */
class CustomerReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_name' => fake()->name(),
            'review_text' => fake()->paragraph(),
            'sentiment' => fake()->randomElement(CustomerReview::SENTIMENTS),
            'sentiment_error' => null,
            'classified_at' => now(),
        ];
    }

    public function withSentiment(string $sentiment): static
    {
        return $this->state(fn (array $attributes): array => [
            'sentiment' => $sentiment,
            'sentiment_error' => null,
            'classified_at' => now(),
        ]);
    }

    public function unclassified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'sentiment' => null,
            'sentiment_error' => null,
            'classified_at' => null,
        ]);
    }

    /**
     * A review whose earlier tagging attempt failed and can be retried.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'sentiment' => null,
            'sentiment_error' => 'A previous tagging attempt failed.',
            'classified_at' => null,
        ]);
    }
}
