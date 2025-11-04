<?php

namespace Features\Webhooks\Infrastructure\Eloquent\Factories;

use Features\Webhooks\Infrastructure\Eloquent\Models\Webhook;
use Features\Webhooks\Infrastructure\Eloquent\Models\WebhookDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookDelivery>
 */
class WebhookDeliveryFactory extends Factory
{
    protected $model = WebhookDelivery::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'webhook_id' => Webhook::factory(),
            'event_name' => fake()->randomElement([
                'user.created',
                'user.updated',
                'organization.created',
                'subscription.created',
            ]),
            'payload' => [
                'id' => fake()->uuid(),
                'type' => fake()->word(),
                'data' => [
                    'name' => fake()->name(),
                    'email' => fake()->email(),
                ],
                'timestamp' => now()->toIso8601String(),
            ],
            'status' => 'pending',
            'status_code' => null,
            'response_body' => null,
            'error_message' => null,
            'attempt' => 1,
            'delivered_at' => null,
        ];
    }

    /**
     * Indicate that the delivery was successful.
     */
    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'success',
            'status_code' => 200,
            'response_body' => json_encode(['success' => true]),
            'delivered_at' => now(),
        ]);
    }

    /**
     * Indicate that the delivery failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'status_code' => fake()->randomElement([400, 404, 500, 502, 503]),
            'error_message' => fake()->sentence(),
        ]);
    }

    /**
     * Set specific attempt number.
     */
    public function attempt(int $attempt): static
    {
        return $this->state(fn (array $attributes) => [
            'attempt' => $attempt,
        ]);
    }

    /**
     * Set for a specific webhook.
     */
    public function forWebhook(Webhook $webhook): static
    {
        return $this->state(fn (array $attributes) => [
            'webhook_id' => $webhook->id,
        ]);
    }
}
