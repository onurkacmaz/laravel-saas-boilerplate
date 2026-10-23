<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeStripeClient;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    config(['app.frontend_url' => 'https://app.example.test']);
    $this->stripe = FakeStripeClient::install();
    [$this->admin, $this->organization] = userInOrganization('admin');
});

function openBillingPortal($user)
{
    return test()->actingAs($user, 'sanctum')
        ->withHeader('X-Organization-Id', test()->organization->id)
        ->postJson('/api/v1/subscriptions/portal');
}

it('opens the Stripe customer portal, back to the billing page', function (): void {
    $this->organization->forceFill(['stripe_id' => 'cus_1'])->save();

    openBillingPortal($this->admin)
        ->assertOk()
        ->assertJsonPath('data.url', 'https://billing.stripe.test/session');

    expect($this->stripe->lastCall('billingPortal.sessions.create'))->toMatchArray([
        'customer' => 'cus_1',
        'return_url' => 'https://app.example.test/settings/billing',
    ]);
});

it('returns to the configured page', function (): void {
    config(['services.stripe.portal_return_path' => 'account/billing']);
    $this->organization->forceFill(['stripe_id' => 'cus_1'])->save();

    openBillingPortal($this->admin)->assertOk();

    expect($this->stripe->lastCall('billingPortal.sessions.create')['return_url'])->toBe('https://app.example.test/account/billing');
});

it('has nothing to open before the organization ever paid', function (): void {
    openBillingPortal($this->admin)
        ->assertNotFound()
        ->assertJsonPath('message', 'There is no billing account yet: subscribe to a paid plan first.');

    expect($this->stripe->calls)->toBe([]);
});

it('hides payment provider errors from the client', function (): void {
    $this->organization->forceFill(['stripe_id' => 'cus_1'])->save();
    $this->stripe->failWith = 'Invalid API Key provided: sk_live_****';

    $response = openBillingPortal($this->admin)->assertStatus(502);

    expect($response->getContent())->not->toContain('sk_live');
});

it('is for admins only', function (): void {
    $this->organization->forceFill(['stripe_id' => 'cus_1'])->save();
    [$member] = userInOrganization('member', $this->organization);

    openBillingPortal($member)->assertForbidden();
});
