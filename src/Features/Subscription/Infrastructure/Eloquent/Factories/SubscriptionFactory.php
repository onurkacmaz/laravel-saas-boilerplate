<?php

namespace Features\Subscription\Infrastructure\Eloquent\Factories;

use App\Models\Organization;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Infrastructure\Eloquent\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'type' => 'default',
            'name' => 'default',
            'stripe_id' => 'sub_'.$this->faker->unique()->lexify('????????????????????????'),
            'stripe_status' => 'active',
            'stripe_price' => null,
            'quantity' => null,
            'trial_ends_at' => null,
            'ends_at' => null,
            'plan_id' => Plan::factory(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'stripe_status' => 'active',
        ]);
    }

    public function trialing(): static
    {
        return $this->state(fn (array $attributes) => [
            'stripe_status' => 'trialing',
            'trial_ends_at' => now()->addDays(14),
        ]);
    }

    public function canceled(): static
    {
        return $this->state(fn (array $attributes) => [
            'stripe_status' => 'canceled',
            'ends_at' => now()->subDay(),
            'canceled_at' => now()->subDay(),
        ]);
    }

    public function pastDue(): static
    {
        return $this->state(fn (array $attributes) => [
            'stripe_status' => 'past_due',
        ]);
    }
}
