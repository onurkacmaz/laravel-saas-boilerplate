<?php

namespace Features\Webhooks\Infrastructure\Eloquent\Factories;

use App\Models\Organization;
use Features\Webhooks\Infrastructure\Eloquent\Models\Webhook;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Webhook>
 */
class WebhookFactory extends Factory
{
    protected $model = Webhook::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->words(3, true),
            'url' => fake()->url(),
            'events' => fake()->randomElements([
                'user.created',
                'user.updated',
                'user.deleted',
                'organization.created',
                'organization.updated',
                'subscription.created',
                'subscription.cancelled',
                'invoice.created',
                'invoice.paid',
            ], fake()->numberBetween(1, 5)),
            'secret' => Str::random(32),
            'is_active' => true,
            'headers' => [
                'X-Custom-Header' => fake()->word(),
            ],
            'timeout' => 30,
            'retry_limit' => 3,
            'last_triggered_at' => null,
        ];
    }

    /**
     * Indicate that the webhook is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set specific events for the webhook.
     */
    public function withEvents(array $events): static
    {
        return $this->state(fn (array $attributes) => [
            'events' => $events,
        ]);
    }

    /**
     * Subscribe to all events.
     */
    public function allEvents(): static
    {
        return $this->state(fn (array $attributes) => [
            'events' => ['*'],
        ]);
    }

    /**
     * Set for a specific organization.
     */
    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (array $attributes) => [
            'organization_id' => $organization->id,
        ]);
    }

    /**
     * Set without a secret.
     */
    public function withoutSecret(): static
    {
        return $this->state(fn (array $attributes) => [
            'secret' => null,
        ]);
    }

    /**
     * Set a custom timeout.
     */
    public function withTimeout(int $seconds): static
    {
        return $this->state(fn (array $attributes) => [
            'timeout' => $seconds,
        ]);
    }
}
