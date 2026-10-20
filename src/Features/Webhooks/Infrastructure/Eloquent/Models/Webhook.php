<?php

namespace Features\Webhooks\Infrastructure\Eloquent\Models;

use App\Models\Organization;
use Carbon\Carbon;
use Features\Webhooks\Infrastructure\Eloquent\Factories\WebhookFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $organization_id
 * @property string $name
 * @property string $url
 * @property array $events
 * @property string|null $secret
 * @property bool $is_active
 * @property array|null $headers
 * @property int $timeout
 * @property int $retry_limit
 * @property Carbon|null $last_triggered_at
 */
class Webhook extends Model
{
    use HasFactory;
    use HasUuids;

    protected static function newFactory(): WebhookFactory
    {
        return WebhookFactory::new();
    }

    protected $hidden = [
        'secret',
    ];

    protected $fillable = [
        'organization_id',
        'name',
        'url',
        'events',
        'secret',
        'is_active',
        'headers',
        'timeout',
        'retry_limit',
        'last_triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'headers' => 'array',
            'is_active' => 'boolean',
            'timeout' => 'integer',
            'retry_limit' => 'integer',
            'last_triggered_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function recentDeliveries(int $limit = 10): HasMany
    {
        return $this->deliveries()->latest()->limit($limit);
    }

    public function successfulDeliveries(): HasMany
    {
        return $this->deliveries()->where('status', 'success');
    }

    public function failedDeliveries(): HasMany
    {
        return $this->deliveries()->where('status', 'failed');
    }

    public function isSubscribedTo(string $eventName): bool
    {
        $events = $this->events ?? [];

        if (in_array('*', $events, true) || in_array($eventName, $events, true)) {
            return true;
        }

        // Wildcard groups such as "invitation.*".
        foreach ($events as $event) {
            if (is_string($event) && str_ends_with($event, '.*') && str_starts_with($eventName, substr($event, 0, -1))) {
                return true;
            }
        }

        return false;
    }

    public function updateLastTriggered(): void
    {
        $this->forceFill(['last_triggered_at' => now()])->saveQuietly();
    }
}
