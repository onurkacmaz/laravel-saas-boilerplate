<?php

namespace Features\Subscription\Infrastructure\Eloquent\Factories;

use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Shared\Enums\PlanInterval;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'slug' => $this->faker->unique()->slug,
            'description' => $this->faker->sentence,
            'price' => $this->faker->randomElement([1000, 2500, 5000]),
            'currency' => 'USD',
            'interval' => $this->faker->randomElement(PlanInterval::cases()),
            'trial_days' => $this->faker->randomElement([0, 7, 14]),
            'is_active' => true,
            'stripe_product_id' => 'prod_'.$this->faker->unique()->lexify('??????????????'),
            'stripe_price_id' => 'price_'.$this->faker->unique()->lexify('??????????????'),
            'features' => ['Feature 1', 'Feature 2', 'Feature 3'],
            'limits' => [
                'users' => 5,
                'projects' => 10,
            ],
        ];
    }
}
