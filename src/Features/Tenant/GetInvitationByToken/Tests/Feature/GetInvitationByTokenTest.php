<?php

use App\Models\Invitation;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->role = Role::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    $this->user->organizations()->attach($this->organization->id, ['role_id' => $this->role->id]);
});

it('retrieves invitation details by token', function (): void {
    $invitation = Invitation::factory()->create([
        'organization_id' => $this->organization->id,
        'role_id' => $this->role->id,
        'email' => 'invited@example.com',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/invitations/token/{$invitation->token}");

    $response->assertOk()
        ->assertJson([
            'data' => [
                'email' => 'invited@example.com',
                'status' => 'pending',
            ],
        ]);
});
