<?php

namespace Features\Audit\Infrastructure\Eloquent\Factories;

use App\Models\Organization;
use App\Models\User;
use Features\Audit\Infrastructure\Eloquent\Models\AuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'event' => fake()->randomElement(['created', 'updated', 'deleted', 'viewed']),
            'auditable_type' => null,
            'auditable_id' => null,
            'old_values' => [],
            'new_values' => fake()->randomElement([
                ['name' => fake()->name()],
                ['email' => fake()->email()],
                ['status' => fake()->randomElement(['active', 'inactive'])],
            ]),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'metadata' => [],
        ];
    }

    /**
     * Indicate that the audit log is for a specific event.
     */
    public function event(string $event): static
    {
        return $this->state(fn (array $attributes) => [
            'event' => $event,
        ]);
    }

    /**
     * Indicate that the audit log is for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Indicate that the audit log is for a specific organization.
     */
    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (array $attributes) => [
            'organization_id' => $organization->id,
        ]);
    }
}
