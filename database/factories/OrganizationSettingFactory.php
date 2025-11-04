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
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
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
            'feature_flags' => [
                'advanced_analytics' => fake()->boolean(),
                'api_access' => fake()->boolean(),
                'custom_branding' => fake()->boolean(),
            ],
        ];
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
