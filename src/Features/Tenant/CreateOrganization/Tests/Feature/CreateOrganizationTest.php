<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();

    Role::factory()->create([
        'slug' => 'owner',
        'name' => 'Owner',
    ]);
});

it('creates an organization for the authenticated user', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/organizations', [
            'name' => 'Acme Corp',
            'description' => 'A test organization',
            'owner_id' => $this->user->id,
        ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'slug',
                'description',
            ],
        ]);

    expect(Organization::where('slug', 'acme-corp')->exists())->toBeTrue();
});
