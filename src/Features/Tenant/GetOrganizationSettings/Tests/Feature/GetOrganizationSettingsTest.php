<?php

use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->role = Role::factory()->create(['organization_id' => $this->organization->id]);

    $this->user->organizations()->attach($this->organization->id, ['role_id' => $this->role->id]);
});

it('retrieves organization settings', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->getJson("/api/v1/organizations/{$this->organization->id}/settings");

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'id',
                'organization_id',
                'timezone',
                'locale',
                'date_format',
                'time_format',
                'currency',
                'notification_preferences',
                'feature_flags',
                'created_at',
                'updated_at',
            ],
        ]);

    expect(OrganizationSetting::where('organization_id', $this->organization->id)->exists())->toBeTrue();
});

it('creates default settings when none exist', function (): void {
    expect(OrganizationSetting::where('organization_id', $this->organization->id)->exists())->toBeFalse();

    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->getJson("/api/v1/organizations/{$this->organization->id}/settings");

    $response->assertStatus(201);

    expect(OrganizationSetting::where('organization_id', $this->organization->id)->exists())->toBeTrue()
        ->and($response->json('data.timezone'))->toBe('Europe/Istanbul')
        ->and($response->json('data.locale'))->toBe('tr')
        ->and($response->json('data.currency'))->toBe('TRY');
});

it('requires authentication to access settings', function (): void {
    $response = $this->getJson("/api/v1/organizations/{$this->organization->id}/settings");

    $response->assertUnauthorized();
});

it('requires tenant header to access settings', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/organizations/{$this->organization->id}/settings");

    $response->assertForbidden();
});
