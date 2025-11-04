<?php

use App\Models\Organization;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Infrastructure\Eloquent\Models\Subscription;
use Features\Subscription\Shared\Events\SubscriptionCreated;
use Features\Subscription\StripeWebhook\Application\StripeWebhookController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Stripe\StripeClient;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('updates subscription metadata and dispatches event after checkout completion', function (): void {
    Event::fake([SubscriptionCreated::class]);

    $organization = Organization::factory()->create([
        'stripe_id' => 'cus_test_123',
    ]);
    $plan = Plan::factory()->create();

    $payload = [
        'type' => 'customer.subscription.created',
        'data' => [
            'object' => [
                'id' => 'sub_test_123',
                'customer' => 'cus_test_123',
                'status' => 'active',
                'current_period_start' => now()->getTimestamp(),
                'items' => [
                    'data' => [
                        [
                            'id' => 'si_test_123',
                            'quantity' => 1,
                            'price' => [
                                'id' => $plan->stripe_price_id,
                                'product' => $plan->stripe_product_id,
                            ],
                        ],
                    ],
                ],
                'metadata' => [
                    'organization_id' => $organization->id,
                    'plan_id' => $plan->id,
                ],
            ],
        ],
    ];

    $controller = new StripeWebhookController;
    $response = $controller->handleCustomerSubscriptionCreated($payload);

    expect($response->getStatusCode())->toBe(200);

    $subscription = $organization->subscriptions()->where('stripe_id', 'sub_test_123')->first();

    expect($subscription)->not->toBeNull()
        ->and($subscription->plan_id)->toBe($plan->id)
        ->and($subscription->starts_at)->not->toBeNull();

    Event::assertDispatched(SubscriptionCreated::class, function (SubscriptionCreated $event) use ($subscription) {
        return $event->subscription->is($subscription);
    });
});

it('cancels previous subscription when metadata references an active subscription', function (): void {
    $fakeStripeClient = new class
    {
        public object $subscriptions;

        public array $cancelled = [];

        public function __construct(array $config = [])
        {
            $this->subscriptions = new class($this)
            {
                public function __construct(private $parent) {}

                public function cancel(string $id, array $parameters): array
                {
                    $this->parent->cancelled[] = [
                        'id' => $id,
                        'parameters' => $parameters,
                    ];

                    return ['id' => $id];
                }
            };
        }
    };

    app()->bind(StripeClient::class, fn () => $fakeStripeClient);

    $organization = Organization::factory()->create([
        'stripe_id' => 'cus_test_prev',
    ]);
    $plan = Plan::factory()->create();

    $previousSubscription = Subscription::factory()->create([
        'organization_id' => $organization->id,
        'stripe_id' => 'sub_prev_123',
        'stripe_status' => 'active',
        'plan_id' => $plan->id,
    ]);

    $payload = [
        'type' => 'customer.subscription.created',
        'data' => [
            'object' => [
                'id' => 'sub_new_123',
                'customer' => 'cus_test_prev',
                'status' => 'active',
                'current_period_start' => now()->getTimestamp(),
                'items' => [
                    'data' => [
                        [
                            'id' => 'si_new_123',
                            'quantity' => 1,
                            'price' => [
                                'id' => $plan->stripe_price_id,
                                'product' => $plan->stripe_product_id,
                            ],
                        ],
                    ],
                ],
                'metadata' => [
                    'organization_id' => $organization->id,
                    'plan_id' => $plan->id,
                    'previous_subscription_id' => $previousSubscription->id,
                    'previous_subscription_stripe_id' => $previousSubscription->stripe_id,
                ],
            ],
        ],
    ];

    $controller = new StripeWebhookController;
    $controller->handleCustomerSubscriptionCreated($payload);

    $previousSubscription->refresh();

    expect($previousSubscription->stripe_status)->toBe('canceled')
        ->and($previousSubscription->ends_at)->not->toBeNull();

    expect($fakeStripeClient->cancelled)->toHaveCount(1)
        ->and($fakeStripeClient->cancelled[0]['id'])->toBe('sub_prev_123');
});
