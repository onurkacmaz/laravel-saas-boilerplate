<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $timezone
 * @property string $locale
 * @property string $date_format
 * @property string $time_format
 * @property string $currency
 * @property array|null $notification_preferences
 * @property array|null $feature_flags
 */
class OrganizationSetting extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'timezone',
        'locale',
        'date_format',
        'time_format',
        'currency',
        'notification_preferences',
        'feature_flags',
    ];

    protected function casts(): array
    {
        return [
            'notification_preferences' => 'array',
            'feature_flags' => 'array',
        ];
    }

    /**
     * Returns the default attribute values for a new organization setting.
     */
    public static function defaultAttributes(): array
    {
        return [
            'timezone' => config('settings.defaults.timezone', 'UTC'),
            'locale' => config('settings.defaults.locale', 'en'),
            'date_format' => config('settings.defaults.date_format', 'Y-m-d'),
            'time_format' => config('settings.defaults.time_format', 'H:i:s'),
            'currency' => config('settings.defaults.currency', 'USD'),
            'notification_preferences' => config('settings.defaults.notification_preferences'),
            'feature_flags' => config('settings.defaults.feature_flags'),
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
