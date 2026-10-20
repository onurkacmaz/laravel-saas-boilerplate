<?php

namespace App\Models;

use Features\Subscription\Infrastructure\Eloquent\Models\Subscription;
use Features\Subscription\Infrastructure\Eloquent\Models\Usage;
use Features\Webhooks\Infrastructure\Eloquent\Models\Webhook;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Cashier\Billable;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $owner_id
 * @property bool $is_active
 * @property string|null $stripe_id
 * @property User $owner
 */
class Organization extends Model
{
    use Billable;
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'owner_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'trial_ends_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_user')
            ->withPivot('role_id')
            ->withTimestamps();
    }

    public function addUser(User $user, string $roleId): void
    {
        $this->users()->syncWithoutDetaching([
            $user->id => ['role_id' => $roleId],
        ]);
    }

    public function removeUser(User $user): void
    {
        $this->users()->detach($user->id);
    }

    public function updateUserRole(User $user, string $roleId): void
    {
        $this->users()->updateExistingPivot($user->id, ['role_id' => $roleId]);
    }

    public function hasUser(User $user): bool
    {
        return $this->users()->where('user_id', $user->id)->exists();
    }

    public function isOwner(User $user): bool
    {
        return $this->owner_id === $user->id;
    }

    public function isAdmin(User $user): bool
    {
        if ($this->isOwner($user)) {
            return true;
        }

        return in_array($this->roleOf($user)?->slug, ['owner', 'admin'], true);
    }

    /**
     * Resolve the role the given user holds in this organization.
     *
     * The tenant scope is bypassed on purpose: system roles are stored with a
     * NULL organization and must be resolvable whatever the current tenant is.
     */
    public function roleOf(User $user): ?Role
    {
        $roleId = $this->users()->where('user_id', $user->id)->first()?->pivot?->role_id;

        if (! $roleId) {
            return null;
        }

        return Role::query()->withoutOrganization()->find($roleId);
    }

    public function activeSubscription(): Subscription|\Laravel\Cashier\Subscription|null
    {
        return $this->subscriptions()
            ->where(function ($query) {
                $query->where('stripe_status', 'active')
                    ->orWhere('stripe_status', 'trialing');
            })
            ->with('plan')
            ->first();
    }

    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscription() !== null;
    }

    public function subscribedToPlan(string $planId): bool
    {
        $subscription = $this->activeSubscription();

        return $subscription !== null && $subscription->plan_id === $planId;
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(Usage::class);
    }

    public function setting(): HasOne
    {
        return $this->hasOne(OrganizationSetting::class);
    }

    public function webhooks(): HasMany
    {
        return $this->hasMany(Webhook::class);
    }

    public function activeWebhooks(): HasMany
    {
        return $this->webhooks()->where('is_active', true);
    }

    public function stripeEmail(): ?string
    {
        return $this->owner?->email;
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'organization_id')
            ->orderByDesc('created_at');
    }
}
