<?php

namespace Tests\Support;

use RuntimeException;
use Stripe\BillingPortal\Session as BillingPortalSession;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\StripeClient;
use Stripe\Subscription;
use Stripe\SubscriptionItem;

/**
 * Minimal in-memory stand-in for the Stripe SDK client used by Cashier.
 *
 * Bind it with FakeStripeClient::install() and inspect the recorded calls.
 */
class FakeStripeClient
{
    public object $customers;

    public object $checkout;

    public object $subscriptions;

    public object $subscriptionItems;

    public object $billingPortal;

    /** @var array<string, list<array>> */
    public array $calls = [];

    public ?string $failWith = null;

    /** Thrown as is by the next calls, e.g. a declined card. */
    public ?\Throwable $throw = null;

    public function __construct()
    {
        $this->customers = new FakeStripeService($this, 'customers', fn (array $params) => Customer::constructFrom([
            'id' => 'cus_'.substr(md5((string) ($params['email'] ?? uniqid())), 0, 14),
            'email' => $params['email'] ?? null,
            'name' => $params['name'] ?? null,
        ]));

        $this->checkout = (object) [
            'sessions' => new FakeStripeService($this, 'checkout.sessions', fn (array $params) => Session::constructFrom([
                'id' => 'cs_test_'.count($this->calls['checkout.sessions.create'] ?? []),
                'url' => 'https://checkout.stripe.test/session',
                'expires_at' => now()->addDay()->getTimestamp(),
                'customer' => $params['customer'] ?? null,
                'mode' => $params['mode'] ?? null,
            ])),
        ];

        $this->subscriptions = new FakeStripeService($this, 'subscriptions', fn (array $params, ?string $id = null) => Subscription::constructFrom([
            'id' => $id ?? 'sub_fake',
            'status' => 'canceled',
            'items' => ['object' => 'list', 'data' => []],
        ]));

        // Periods live on the items: Cashier reads the end of the period from them.
        $this->subscriptionItems = new FakeStripeService($this, 'subscriptionItems', fn (array $params, ?string $id = null) => SubscriptionItem::constructFrom([
            'id' => $id ?? 'si_fake',
            'current_period_start' => now()->startOfDay()->getTimestamp(),
            'current_period_end' => now()->startOfDay()->addMonth()->getTimestamp(),
        ]));

        $this->billingPortal = (object) [
            'sessions' => new FakeStripeService($this, 'billingPortal.sessions', fn (array $params) => BillingPortalSession::constructFrom([
                'id' => 'bps_test_'.count($this->calls['billingPortal.sessions.create'] ?? []),
                'url' => 'https://billing.stripe.test/session',
                'customer' => $params['customer'] ?? null,
                'return_url' => $params['return_url'] ?? null,
            ])),
        ];
    }

    public static function install(): self
    {
        $fake = new self;

        app()->bind(StripeClient::class, fn () => $fake);

        return $fake;
    }

    public function record(string $call, array $arguments): void
    {
        if ($this->failWith !== null) {
            throw new RuntimeException($this->failWith);
        }

        if ($this->throw !== null) {
            throw $this->throw;
        }

        $this->calls[$call][] = $arguments;
    }

    public function lastCall(string $call): ?array
    {
        $calls = $this->calls[$call] ?? [];

        return $calls === [] ? null : end($calls);
    }
}

class FakeStripeService
{
    /**
     * @param  \Closure(array, ?string): object  $factory
     */
    public function __construct(
        private readonly FakeStripeClient $client,
        private readonly string $name,
        private readonly \Closure $factory,
    ) {}

    public function create(array $params = [], array $options = []): object
    {
        $this->client->record($this->name.'.create', $params);

        return ($this->factory)($params, null);
    }

    public function cancel(string $id, array $params = [], array $options = []): object
    {
        $this->client->record($this->name.'.cancel', ['id' => $id, ...$params]);

        return ($this->factory)($params, $id);
    }

    public function retrieve(string $id, array $params = [], array $options = []): object
    {
        $this->client->record($this->name.'.retrieve', ['id' => $id, ...$params]);

        return ($this->factory)($params, $id);
    }

    public function update(string $id, array $params = [], array $options = []): object
    {
        $this->client->record($this->name.'.update', ['id' => $id, ...$params]);

        // Scheduling a cancellation keeps the subscription active until the period ends.
        // A swap answers with the items it leaves on the subscription.
        if ($this->name === 'subscriptions') {
            $items = collect($params['items'] ?? [])
                ->reject(fn (array $item) => ! empty($item['deleted']) || empty($item['price']))
                ->map(fn (array $item) => [
                    'id' => $item['id'] ?? 'si_'.$item['price'],
                    'object' => 'subscription_item',
                    'price' => ['id' => $item['price'], 'product' => 'prod_fake'],
                    'quantity' => $item['quantity'] ?? 1,
                ])
                ->values()
                ->all();

            $subscription = ['id' => $id, 'status' => 'active', ...collect($params)->except(['items', 'expand'])->all()];

            if ($items !== []) {
                $subscription['items'] = ['object' => 'list', 'data' => $items];
            }

            return Subscription::constructFrom($subscription);
        }

        return ($this->factory)($params, $id);
    }
}
