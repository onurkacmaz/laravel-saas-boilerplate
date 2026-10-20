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
    $this->role = Role::factory()->create(['organization_id' => $this->organization->id, 'slug' => 'admin']);

    $this->user->organizations()->attach($this->organization->id, ['role_id' => $this->role->id]);
});

it('cancels a pending invitation', function (): void {
    $invitation = Invitation::factory()->create([
        'organization_id' => $this->organization->id,
        'role_id' => $this->role->id,
        'invited_by' => $this->user->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->deleteJson("/api/v1/invitations/{$invitation->id}");

    $response->assertNoContent();

    $invitation->refresh();
    expect($invitation->status)->toBe('canceled');
});
