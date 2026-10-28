<?php

namespace Features\Subscription\Infrastructure\Paddle;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Paddle\Billable;

/**
 * An organization as Cashier Paddle sees it: the same row of the
 * organizations table, with Cashier Paddle's Billable trait. Organization
 * itself carries Cashier's (Stripe) trait, whose methods share the names of
 * this one, so the two cannot live on the same model.
 *
 * @property string $id
 * @property string $name
 * @property string $owner_id
 * @property User|null $owner
 */
class PaddleBillable extends Model
{
    use Billable, HasUuids;

    protected $table = 'organizations';

    protected $guarded = ['*'];

    public static function of(Organization $organization): self
    {
        return self::query()->findOrFail($organization->getKey());
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function paddleName(): ?string
    {
        return $this->name;
    }

    /** Paddle sends receipts to the organization's owner. */
    public function paddleEmail(): ?string
    {
        return $this->owner?->email;
    }
}
