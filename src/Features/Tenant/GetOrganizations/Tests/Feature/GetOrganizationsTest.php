<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

it('lists organizations the user belongs to', function (): void {
    $org1 = Organization::factory()->create();
    $org2 = Organization::factory()->create();

    $role1 = Role::factory()->create(['organization_id' => $org1->id]);
    $role2 = Role::factory()->create(['organization_id' => $org2->id]);

    $this->user->organizations()->attach([
        $org1->id => ['role_id' => $role1->id],
        $org2->id => ['role_id' => $role2->id],
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/organizations');

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

it('requires authentication to list organizations', function (): void {
    $response = $this->getJson('/api/v1/organizations');

    $response->assertUnauthorized();
});
