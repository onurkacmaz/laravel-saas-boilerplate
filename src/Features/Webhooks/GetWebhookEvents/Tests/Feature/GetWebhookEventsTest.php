<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('lists the events webhooks can subscribe to', function (): void {
    [$admin, $organization] = userInOrganization('admin');
    config(['webhooks.events' => [...config('webhooks.events'), 'project.created', 'member.added', '']]);

    $events = $this->actingAs($admin, 'sanctum')->withHeader('X-Organization-Id', $organization->id)
        ->getJson('/api/v1/webhooks/events')
        ->assertOk()
        ->json('data');

    expect($events)->toContain('member.added', 'payment.failed', 'project.created')
        ->and($events)->not->toContain('')
        ->and(count($events))->toBe(count(array_unique($events)))
        ->and($events)->toBe(collect($events)->sort()->values()->all());
});

it('is reserved to organization admins', function (): void {
    [$member, $organization] = userInOrganization('member');

    $this->actingAs($member, 'sanctum')->withHeader('X-Organization-Id', $organization->id)
        ->getJson('/api/v1/webhooks/events')
        ->assertForbidden();
});
