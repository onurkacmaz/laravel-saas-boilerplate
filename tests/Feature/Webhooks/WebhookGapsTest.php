<?php

use App\Models\Invitation;
use Features\Subscription\Infrastructure\Eloquent\Models\Subscription;
use Features\Subscription\Shared\Events\PaymentFailed;
use Features\Subscription\Shared\Events\PaymentSucceeded;
use Features\Subscription\Shared\Events\SubscriptionCanceled;
use Features\Subscription\Shared\Events\SubscriptionCreated;
use Features\Subscription\Shared\Events\SubscriptionUpdated;
use Features\Tenant\AcceptInvitation\Events\InvitationAccepted;
use Features\Tenant\CancelInvitation\Events\InvitationCanceled;
use Features\Tenant\DeleteOrganization\Events\OrganizationDeleted;
use Features\Tenant\RejectInvitation\Events\InvitationRejected;
use Features\Tenant\RemoveUserFromOrganization\Events\UserRemovedFromOrganization;
use Features\Tenant\SendInvitation\Events\InvitationSent;
use Features\Tenant\UpdateOrganization\Events\OrganizationUpdated;
use Features\Tenant\UpdateUserRoleOnOrganization\Events\UserRoleUpdated;
use Features\Webhooks\DeliverWebhook\Application\DeliverWebhook;
use Features\Webhooks\DeliverWebhook\Application\DeliverWebhookHandler;
use Features\Webhooks\Infrastructure\Eloquent\Models\Webhook;
use Features\Webhooks\Infrastructure\Eloquent\Models\WebhookDelivery;
use Features\Webhooks\Shared\Jobs\DispatchWebhookJob;
use Features\Webhooks\Shared\Support\WebhookDispatcher;
use Features\Webhooks\Shared\Support\WebhookRules;
use Features\Webhooks\Shared\Support\WebhookUrlGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('maps every domain event to its webhook event', function (): void {
    Queue::fake();

    [$admin, $organization, $role] = userInOrganization('admin');
    Webhook::factory()->forOrganization($organization)->allEvents()->create();
    $invitation = Invitation::factory()->create(['organization_id' => $organization->id]);
    $subscription = Subscription::factory()->create(['organization_id' => $organization->id]);

    $events = [
        new OrganizationUpdated($organization),
        new OrganizationDeleted($organization),
        new UserRemovedFromOrganization($organization, $admin),
        new UserRoleUpdated($organization, $admin, $role->id),
        new InvitationSent($invitation),
        new InvitationAccepted($invitation, $admin),
        new InvitationRejected($invitation),
        new InvitationCanceled($invitation),
        new SubscriptionCreated($subscription),
        new SubscriptionUpdated($subscription),
        new SubscriptionCanceled($subscription),
        new PaymentSucceeded($organization, ['id' => 'in_1', 'amount_paid' => 100, 'currency' => 'usd']),
        new PaymentFailed($organization, ['id' => 'in_2', 'amount_due' => 100, 'currency' => 'usd']),
    ];

    foreach ($events as $event) {
        event($event);
    }

    $names = collect(Queue::pushed(DispatchWebhookJob::class))->map(fn ($job) => $job->eventName)->all();

    expect($names)->toBe([
        'organization.updated', 'organization.deleted', 'member.removed', 'member.role_updated',
        'invitation.sent', 'invitation.accepted', 'invitation.rejected', 'invitation.canceled',
        'subscription.created', 'subscription.updated', 'subscription.canceled',
        'payment.succeeded', 'payment.failed',
    ]);
});

it('never lets a webhook problem break the triggering action', function (): void {
    $this->mock(WebhookDispatcher::class)->shouldReceive('dispatch')->andThrow(new RuntimeException('queue down'));

    [$admin, $organization] = userInOrganization('admin');

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->putJson("/api/v1/organizations/{$organization->id}", ['description' => 'Still works'])
        ->assertOk();
});

it('does nothing without an organization', function (): void {
    expect(app(WebhookDispatcher::class)->dispatch(null, 'member.added'))->toBe(0);
});

it('rotates the secret and sanitizes headers on update', function (): void {
    [$admin, $organization] = userInOrganization('admin');
    $webhook = Webhook::factory()->forOrganization($organization)->create(['secret' => 'old-secret-value-123']);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->putJson("/api/v1/webhooks/{$webhook->id}", [
            'secret' => null,
            'events' => ['member.added', 'member.removed'],
            'headers' => ['X-Tenant' => 'acme', 'Host' => 'evil.test', 'Bad Header' => 'x'],
        ])
        ->assertOk();

    $webhook->refresh();

    expect($webhook->secret)->not->toBe('old-secret-value-123')->toHaveLength(48)
        ->and($webhook->events)->toBe(['member.added', 'member.removed'])
        ->and($webhook->headers)->toBe(['X-Tenant' => 'acme']);
});

it('searches webhooks and filters deliveries', function (): void {
    [$admin, $organization] = userInOrganization('admin');
    $billing = Webhook::factory()->forOrganization($organization)->create(['name' => 'Billing sync', 'url' => 'https://billing.example.com/hook']);
    Webhook::factory()->forOrganization($organization)->create(['name' => 'Slack', 'url' => 'https://hooks.example.com/slack']);
    WebhookDelivery::factory()->forWebhook($billing)->successful()->count(2)->create();
    WebhookDelivery::factory()->forWebhook($billing)->failed()->create();

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->getJson('/api/v1/webhooks?search=billing')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $billing->id);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->getJson("/api/v1/webhooks/{$billing->id}/deliveries?status=failed")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('records network errors and blocked targets as failed deliveries', function (): void {
    Http::fake(fn () => throw new ConnectionException('Connection timed out'));

    $webhook = Webhook::factory()->create(['url' => 'https://example.com/hook', 'events' => ['*']]);

    $delivery = (new DeliverWebhookHandler)(
        new DeliverWebhook($webhook, 'member.added', ['a' => 1])
    );

    expect($delivery->status)->toBe('failed')
        ->and($delivery->error_message)->toContain('Connection timed out');

    $internal = Webhook::factory()->create(['url' => 'http://127.0.0.1/hook', 'events' => ['*']]);

    $blocked = (new DeliverWebhookHandler)(
        new DeliverWebhook($internal, 'member.added', ['a' => 1])
    );

    expect($blocked->status)->toBe('failed')
        ->and($blocked->error_message)->toContain('blocked');
});

it('stops retrying once the retry limit is reached', function (): void {
    Queue::fake();
    Http::fake(['*' => Http::response('down', 500)]);

    $webhook = Webhook::factory()->create(['url' => 'https://example.com/hook', 'events' => ['*'], 'retry_limit' => 2]);

    (new DispatchWebhookJob($webhook, 'member.added', [], attempt: 2))->handle();

    Queue::assertNothingPushed();
    expect($webhook->deliveries()->first()->attempt)->toBe(2)
        ->and((new DispatchWebhookJob($webhook, 'x', []))->tags())->toContain('webhook:'.$webhook->id);
});

it('allows private targets only when explicitly enabled', function (): void {
    expect(WebhookUrlGuard::isAllowed('http://10.0.0.1/hook'))->toBeFalse()
        ->and(WebhookUrlGuard::isAllowed('not a url'))->toBeFalse()
        ->and(WebhookUrlGuard::isAllowed('https://api.internal/hook'))->toBeFalse()
        ->and(WebhookUrlGuard::isAllowed('https://[2001:4860:4860::8888]/hook'))->toBeTrue();

    config(['webhooks.allow_private_urls' => true]);

    expect(WebhookUrlGuard::isAllowed('http://10.0.0.1/hook'))->toBeTrue();
});

it('drops reserved and malformed custom headers', function (): void {
    expect(WebhookRules::sanitizeHeaders([
        'X-Ok' => "value\r\nInjected: yes",
        'Content-Type' => 'text/plain',
        'X-Array' => ['nested'],
        0 => 'numeric',
    ]))->toBe(['X-Ok' => 'valueInjected: yes'])
        ->and(WebhookRules::sanitizeHeaders(null))->toBe([]);
});
