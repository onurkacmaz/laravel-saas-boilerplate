<?php

namespace Database\Seeders;

use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Shared\Enums\PlanInterval;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Limits and features that modules add to the catalogue, by plan slug.
     *
     * @var array<string, array{limits: array<string, mixed>, features: list<string>}>
     */
    private static array $extensions = [];

    /**
     * Add limits and features to a plan of the catalogue, e.g. for a module's
     * own resources. Call it from a service provider's boot method; the
     * seeder merges them into the plan.
     *
     * @param  array<string, mixed>  $limits
     * @param  list<string>  $features
     */
    public static function extend(string $slug, array $limits = [], array $features = []): void
    {
        $extension = self::$extensions[$slug] ?? ['limits' => [], 'features' => []];

        self::$extensions[$slug] = [
            'limits' => [...$extension['limits'], ...$limits],
            'features' => array_values(array_unique([...$extension['features'], ...$features])),
        ];
    }

    public static function forgetExtensions(): void
    {
        self::$extensions = [];
    }

    /**
     * Seed a sensible default plan catalogue. Stripe identifiers are read from
     * the environment so the paid plans can be wired to real Stripe prices.
     */
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'free',
                'name' => 'Free',
                'description' => 'Get started for free.',
                'price' => 0,
                'interval' => PlanInterval::MONTHLY,
                'trial_days' => 0,
                'stripe_product_id' => null,
                'stripe_price_id' => null,
                'features' => ['basic-support'],
                'limits' => ['users' => 3, 'projects' => 1, 'api_calls' => 1000, 'requests_per_minute' => 60],
            ],
            [
                'slug' => 'pro-monthly',
                'name' => 'Pro',
                'description' => 'For growing teams.',
                'price' => 2900,
                'interval' => PlanInterval::MONTHLY,
                'trial_days' => 14,
                'stripe_product_id' => env('STRIPE_PRODUCT_PRO'),
                'stripe_price_id' => env('STRIPE_PRICE_PRO_MONTHLY'),
                'features' => ['basic-support', 'priority-support', 'webhooks', 'audit-logs'],
                'limits' => ['users' => 25, 'projects' => 25, 'api_calls' => 100000, 'requests_per_minute' => 600],
            ],
            [
                'slug' => 'pro-yearly',
                'name' => 'Pro (yearly)',
                'description' => 'For growing teams, billed yearly.',
                'price' => 29000,
                'interval' => PlanInterval::YEARLY,
                'trial_days' => 14,
                'stripe_product_id' => env('STRIPE_PRODUCT_PRO'),
                'stripe_price_id' => env('STRIPE_PRICE_PRO_YEARLY'),
                'features' => ['basic-support', 'priority-support', 'webhooks', 'audit-logs'],
                'limits' => ['users' => 25, 'projects' => 25, 'api_calls' => 100000, 'requests_per_minute' => 600],
            ],
        ];

        foreach ($plans as $plan) {
            $extension = self::$extensions[$plan['slug']] ?? ['limits' => [], 'features' => []];
            $plan['limits'] = [...$plan['limits'], ...$extension['limits']];
            $plan['features'] = array_values(array_unique([...$plan['features'], ...$extension['features']]));

            Plan::query()->updateOrCreate(
                ['slug' => $plan['slug']],
                [...$plan, 'currency' => strtoupper((string) config('cashier.currency', 'usd')), 'is_active' => true],
            );
        }
    }
}
