<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Features\Webhooks\Infrastructure\Eloquent\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create([
        'owner_id' => $this->user->id,
    ]);

    $this->role = Role::factory()->create([
        'organization_id' => $this->organization->id,
        'slug' => 'owner',
    ]);

    $this->user->organizations()->attach($this->organization->id, [
        'role_id' => $this->role->id,
    ]);
});

it('lists paginated webhooks for organization', function (): void {
    Webhook::factory()->count(3)->forOrganization($this->organization)->create();

    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->getJson('/api/v1/webhooks');

    $response->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.total', 3)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'url', 'events', 'is_active'],
            ],
            'links',
            'meta',
        ]);
});

it('requires authentication to access webhook endpoints', function (): void {
    $response = $this->getJson('/api/v1/webhooks');

    $response->assertUnauthorized();
});

it('requires tenant header for scoped routes', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/webhooks');

    $response->assertForbidden()
        ->assertJsonPath('message', 'Organization context is required. Please provide X-Organization-Id header.');
});
