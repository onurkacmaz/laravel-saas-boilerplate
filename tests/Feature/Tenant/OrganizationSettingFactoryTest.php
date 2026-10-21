<?php

use App\Models\OrganizationSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates the settings a new organization gets', function (): void {
    $settings = OrganizationSetting::factory()->create();

    expect($settings->only(array_keys(OrganizationSetting::defaultAttributes())))
        ->toEqual(OrganizationSetting::defaultAttributes());
});

it('randomizes settings on request, with the configured flags only', function (): void {
    $settings = OrganizationSetting::factory()->randomized()->create();

    expect(array_keys($settings->feature_flags))->toBe(config('settings.feature_flags'))
        ->and(array_keys($settings->notification_preferences))->toBe(['email', 'push', 'sms']);
});
