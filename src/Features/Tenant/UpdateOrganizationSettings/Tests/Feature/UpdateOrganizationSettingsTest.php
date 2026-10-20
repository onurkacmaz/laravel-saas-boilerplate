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
    $this->role = Role::factory()->create(['organization_id' => $this->organization->id, 'slug' => 'admin']);

    $this->user->organizations()->attach($this->organization->id, ['role_id' => $this->role->id]);
});

it('updates organization settings', function (): void {
    $settings = OrganizationSetting::factory()->forOrganization($this->organization)->create();

    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->putJson("/api/v1/organizations/{$this->organization->id}/settings", [
            'timezone' => 'Europe/Istanbul',
            'locale' => 'tr',
            'currency' => 'TRY',
        ]);

    $response->assertOk()
        ->assertJson([
            'data' => [
                'timezone' => 'Europe/Istanbul',
                'locale' => 'tr',
                'currency' => 'TRY',
            ],
        ]);

    $settings->refresh();

    expect($settings->timezone)->toBe('Europe/Istanbul')
        ->and($settings->locale)->toBe('tr')
        ->and($settings->currency)->toBe('TRY');
});

it('updates notification preferences', function (): void {
    OrganizationSetting::factory()->forOrganization($this->organization)->create();

    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->putJson("/api/v1/organizations/{$this->organization->id}/settings", [
            'notification_preferences' => [
                'email' => true,
                'push' => false,
                'sms' => true,
            ],
        ]);

    $response->assertOk();

    $settings = OrganizationSetting::where('organization_id', $this->organization->id)->first();

    expect($settings->notification_preferences['email'])->toBeTrue()
        ->and($settings->notification_preferences['push'])->toBeFalse()
        ->and($settings->notification_preferences['sms'])->toBeTrue();
});

it('updates feature flags', function (): void {
    OrganizationSetting::factory()->forOrganization($this->organization)->create();

    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->putJson("/api/v1/organizations/{$this->organization->id}/settings", [
            'feature_flags' => [
                'advanced_analytics' => true,
                'api_access' => true,
                'custom_branding' => false,
            ],
        ]);

    $response->assertOk();

    $settings = OrganizationSetting::where('organization_id', $this->organization->id)->first();

    expect($settings->feature_flags['advanced_analytics'])->toBeTrue()
        ->and($settings->feature_flags['api_access'])->toBeTrue()
        ->and($settings->feature_flags['custom_branding'])->toBeFalse();
});

it('validates timezone format', function (): void {
    OrganizationSetting::factory()->forOrganization($this->organization)->create();

    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->putJson("/api/v1/organizations/{$this->organization->id}/settings", [
            'timezone' => 'Invalid/Timezone',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['timezone']);
});

it('validates locale values', function (): void {
    OrganizationSetting::factory()->forOrganization($this->organization)->create();

    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->putJson("/api/v1/organizations/{$this->organization->id}/settings", [
            'locale' => 'invalid_locale',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['locale']);
});

it('validates date format values', function (): void {
    OrganizationSetting::factory()->forOrganization($this->organization)->create();

    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->putJson("/api/v1/organizations/{$this->organization->id}/settings", [
            'date_format' => 'invalid-format',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['date_format']);
});

it('validates time format values', function (): void {
    OrganizationSetting::factory()->forOrganization($this->organization)->create();

    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->putJson("/api/v1/organizations/{$this->organization->id}/settings", [
            'time_format' => 'invalid-format',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['time_format']);
});

it('validates currency code length', function (): void {
    OrganizationSetting::factory()->forOrganization($this->organization)->create();

    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->putJson("/api/v1/organizations/{$this->organization->id}/settings", [
            'currency' => 'US',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['currency']);
});
