<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create(['name' => 'Test Org']);
    $this->role = Role::factory()->create(['organization_id' => $this->organization->id]);

    $this->user->organizations()->attach($this->organization->id, ['role_id' => $this->role->id]);
});

it('displays organization details with tenant context', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->getJson("/api/v1/organizations/{$this->organization->id}");

    $response->assertOk()
        ->assertJson([
            'data' => [
                'id' => $this->organization->id,
                'name' => 'Test Org',
            ],
        ]);
});
