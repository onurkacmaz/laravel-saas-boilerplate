<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OrganizationSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationSetting>
 */
class OrganizationSettingFactory extends Factory
{
    protected $model = OrganizationSetting::class;

    /**
     * The settings a new organization gets in production, so tests do not
     * depend on random preferences or flags. Use the states below to change
     * them.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            ...OrganizationSetting::defaultAttributes(),
        ];
    }

    /**
     * Random but valid settings, e.g. to check that a feature copes with any
     * combination.
     */
    public function randomized(): static
    {
        return $this->state(fn (array $attributes) => [
            'timezone' => fake()->timezone(),
            'locale' => fake()->randomElement(['en', 'tr', 'de', 'fr', 'es']),
            'date_format' => fake()->randomElement(['Y-m-d', 'd/m/Y', 'm/d/Y', 'd.m.Y']),
            'time_format' => fake()->randomElement(['H:i:s', 'h:i:s A', 'H:i']),
            'currency' => fake()->currencyCode(),
            'notification_preferences' => [
                'email' => fake()->boolean(),
                'push' => fake()->boolean(),
                'sms' => fake()->boolean(),
            ],
            'feature_flags' => collect(config('settings.feature_flags', []))
                ->mapWithKeys(fn (string $flag) => [$flag => fake()->boolean()])
                ->all(),
        ]);
    }

    /**
     * Set notification preferences.
     */
    public function withNotifications(array $preferences): static
    {
        return $this->state(fn (array $attributes) => [
            'notification_preferences' => $preferences,
        ]);
    }

    /**
     * Set feature flags.
     */
    public function withFeatures(array $features): static
    {
        return $this->state(fn (array $attributes) => [
            'feature_flags' => $features,
        ]);
    }

    /**
     * Set for a specific organization.
     */
    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (array $attributes) => [
            'organization_id' => $organization->id,
        ]);
    }
}
