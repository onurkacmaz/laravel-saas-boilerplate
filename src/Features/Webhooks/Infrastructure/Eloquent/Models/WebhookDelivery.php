<?php

namespace Features\Webhooks\Infrastructure\Eloquent\Models;

use Carbon\Carbon;
use Features\Webhooks\Infrastructure\Eloquent\Factories\WebhookDeliveryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $webhook_id
 * @property string $event_name
 * @property array $payload
 * @property string $status
 * @property int|null $status_code
 * @property string|null $response_body
 * @property string|null $error_message
 * @property int $attempt
 * @property Carbon|null $delivered_at
 */
class WebhookDelivery extends Model
{
    use HasFactory;
    use HasUuids;

    protected static function newFactory(): WebhookDeliveryFactory
    {
        return WebhookDeliveryFactory::new();
    }

    protected $fillable = [
        'webhook_id',
        'event_name',
        'payload',
        'status',
        'status_code',
        'response_body',
        'error_message',
        'attempt',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status_code' => 'integer',
            'attempt' => 'integer',
            'delivered_at' => 'datetime',
        ];
    }

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }

    public function markAsSuccess(int $statusCode, ?string $responseBody = null): void
    {
        $this->update([
            'status' => 'success',
            'status_code' => $statusCode,
            'response_body' => $responseBody,
            'delivered_at' => now(),
        ]);
    }

    public function markAsFailed(?int $statusCode = null, ?string $errorMessage = null, ?string $responseBody = null): void
    {
        $this->update([
            'status' => 'failed',
            'status_code' => $statusCode,
            'error_message' => $errorMessage,
            'response_body' => $responseBody,
        ]);
    }

    public function incrementAttempt(): void
    {
        $this->increment('attempt');
    }

    public function canRetry(): bool
    {
        return $this->status === 'failed' && $this->attempt < (int) $this->webhook?->retry_limit;
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
