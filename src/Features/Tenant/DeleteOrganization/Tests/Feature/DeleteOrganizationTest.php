<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->role = Role::factory()->create(['organization_id' => $this->organization->id, 'slug' => 'owner']);

    $this->user->organizations()->attach($this->organization->id, ['role_id' => $this->role->id]);
});

it('deletes an organization', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->deleteJson("/api/v1/organizations/{$this->organization->id}");

    $response->assertNoContent();

    expect(Organization::find($this->organization->id))->toBeNull();
});
