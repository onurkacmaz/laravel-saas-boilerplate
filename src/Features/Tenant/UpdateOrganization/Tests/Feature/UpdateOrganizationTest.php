<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create(['name' => 'Old Name']);
    $this->role = Role::factory()->create(['organization_id' => $this->organization->id, 'slug' => 'admin']);

    $this->user->organizations()->attach($this->organization->id, ['role_id' => $this->role->id]);
});

it('updates organization details', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->putJson("/api/v1/organizations/{$this->organization->id}", [
            'name' => 'New Name',
            'description' => 'Updated description',
        ]);

    $response->assertOk()
        ->assertJson([
            'data' => [
                'name' => 'New Name',
            ],
        ]);

    $this->organization->refresh();
    expect($this->organization->name)->toBe('New Name');
});
