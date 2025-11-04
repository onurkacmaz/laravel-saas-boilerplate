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

it('updates webhook configuration', function (): void {
    $webhook = Webhook::factory()->forOrganization($this->organization)->create([
        'name' => 'Old Name',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->putJson("/api/v1/webhooks/{$webhook->id}", [
            'name' => 'New Name',
            'is_active' => false,
            'events' => ['user.deleted'],
        ]);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Webhook updated successfully')
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.events.0', 'user.deleted');

    $webhook->refresh();
    expect($webhook->name)->toBe('New Name');
    expect($webhook->is_active)->toBeFalse();
});
