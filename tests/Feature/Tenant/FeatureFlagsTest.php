<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('accepts the feature flags listed in the configuration', function (): void {
    config(['settings.feature_flags' => [...config('settings.feature_flags'), 'status_pages']]);
    [$admin, $organization] = userInOrganization('admin');
    $update = fn (array $flags) => $this->actingAs($admin, 'sanctum')->withHeader('X-Organization-Id', $organization->id)
        ->putJson("/api/v1/organizations/{$organization->id}/settings", ['feature_flags' => $flags]);

    $update(['status_pages' => true, 'custom_branding' => true])
        ->assertOk()
        ->assertJsonPath('data.feature_flags.status_pages', true)
        ->assertJsonPath('data.feature_flags.custom_branding', true);

    $update(['status_pages' => 'yes'])->assertJsonValidationErrors('feature_flags.status_pages');
    $update(['unknown_flag' => true])->assertJsonValidationErrors('feature_flags');
});

it('keeps the default flags', function (): void {
    expect(config('settings.feature_flags'))->toBe(['advanced_analytics', 'api_access', 'custom_branding']);
});
