<?php

namespace Tests\Support;

use RuntimeException;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\StripeClient;
use Stripe\Subscription;

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

    /** @var array<string, list<array>> */
    public array $calls = [];

    public ?string $failWith = null;

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
        if ($this->name === 'subscriptions') {
            return Subscription::constructFrom(['id' => $id, 'status' => 'active', ...$params]);
        }

        return ($this->factory)($params, $id);
    }
}
