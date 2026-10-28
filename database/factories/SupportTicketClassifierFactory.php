<?php

namespace Database\Factories;

use App\Models\SupportTicketClassifier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicketClassifier>
 */
class SupportTicketClassifierFactory extends Factory
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
            'company_name' => fake()->company(),
            'email' => fake()->unique()->safeEmail(),
            'ticket_subject' => fake()->sentence(6),
            'ticket_message' => fake()->paragraph(),
            'ticket_category' => fake()->randomElement(SupportTicketClassifier::CATEGORIES),
            'urgency_level' => fake()->randomElement(SupportTicketClassifier::URGENCY_LEVELS),
            'ai_summary' => fake()->sentence(),
            'status' => SupportTicketClassifier::STATUS_OPEN,
            'classified_at' => now(),
            'closed_at' => null,
        ];
    }

    public function unclassified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'ticket_category' => null,
            'urgency_level' => null,
            'ai_summary' => null,
            'classified_at' => null,
        ]);
    }

    /**
     * A ticket whose queued classification is still in progress.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'ticket_category' => null,
            'urgency_level' => null,
            'ai_summary' => null,
            'classified_at' => null,
            'classification_requested_at' => now(),
            'classification_error' => null,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SupportTicketClassifier::STATUS_CLOSED,
            'closed_at' => now(),
        ]);
    }
}
