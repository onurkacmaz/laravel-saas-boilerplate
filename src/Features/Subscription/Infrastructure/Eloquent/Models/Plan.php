<?php

namespace Features\Subscription\Infrastructure\Eloquent\Models;

use Features\Subscription\Infrastructure\Eloquent\Factories\PlanFactory;
use Features\Subscription\Shared\Enums\PlanInterval;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $price
 * @property string $currency
 * @property PlanInterval $interval
 * @property int|null $trial_days
 * @property bool $is_active
 * @property string|null $stripe_product_id
 * @property string|null $stripe_price_id
 * @property array $features
 * @property array $limits
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class Plan extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'currency',
        'interval',
        'trial_days',
        'is_active',
        'stripe_product_id',
        'stripe_price_id',
        'features',
        'limits',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'trial_days' => 'integer',
            'is_active' => 'boolean',
            'interval' => PlanInterval::class,
            'features' => 'array',
            'limits' => 'array',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function getLimit(string $key, mixed $default = null): mixed
    {
        return data_get($this->limits, $key, $default);
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }

    public function getStripePriceId(): ?string
    {
        return $this->stripe_price_id;
    }

    public function getStripeProductId(): ?string
    {
        return $this->stripe_product_id;
    }

    public function getFormattedPrice(): string
    {
        return number_format($this->price / 100, 2).' '.$this->currency;
    }

    public function isFree(): bool
    {
        return $this->price === 0;
    }

    public static function newFactory(): PlanFactory
    {
        return PlanFactory::new();
    }
}
