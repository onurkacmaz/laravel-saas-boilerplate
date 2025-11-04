<?php

namespace Database\Factories;

use App\Models\Invitation;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Features\Tenant\Shared\Enums\InvitationStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    protected $model = Invitation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'email' => $this->faker->unique()->safeEmail(),
            'invited_by' => User::factory(),
            'role_id' => Role::factory(),
            'status' => InvitationStatus::PENDING->value,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
        ];
    }

    /**
     * Indicate that the invitation is pending.
     */
    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => InvitationStatus::PENDING->value,
            'accepted_at' => null,
            'expires_at' => now()->addDays(7),
        ]);
    }

    /**
     * Indicate that the invitation has been accepted.
     */
    public function accepted(): static
    {
        return $this->state(fn () => [
            'status' => InvitationStatus::ACCEPTED->value,
            'accepted_at' => now(),
        ]);
    }

    /**
     * Indicate that the invitation has been rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => InvitationStatus::REJECTED->value,
            'accepted_at' => null,
        ]);
    }

    /**
     * Indicate that the invitation has expired.
     */
    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => InvitationStatus::EXPIRED->value,
            'accepted_at' => null,
            'expires_at' => now()->subDay(),
        ]);
    }

    /**
     * Indicate that the invitation has been canceled.
     */
    public function canceled(): static
    {
        return $this->state(fn () => [
            'status' => InvitationStatus::CANCELED->value,
            'accepted_at' => null,
        ]);
    }
}
