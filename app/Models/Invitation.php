<?php

namespace App\Models;

use Features\Tenant\Shared\Enums\InvitationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $email
 * @property string|null $invited_by
 * @property string $role_id
 * @property string $status
 * @property string $token
 * @property Carbon|null $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property Organization $organization
 * @property Role $role
 * @property User|null $invitedBy
 */
class Invitation extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'email',
        'invited_by',
        'role_id',
        'status',
        'token',
        'expires_at',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Invitation $invitation) {
            if (! $invitation->token) {
                $invitation->token = Str::random(64);
            }

            if (! $invitation->expires_at) {
                $invitation->expires_at = now()->addDays(7);
            }

            if (! $invitation->status) {
                $invitation->status = InvitationStatus::PENDING->value;
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isPending(): bool
    {
        return $this->statusEnum() === InvitationStatus::PENDING
            && $this->expires_at
            && $this->expires_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->statusEnum() === InvitationStatus::EXPIRED
            || ($this->expires_at && $this->expires_at->isPast());
    }

    public function isAccepted(): bool
    {
        return $this->statusEnum() === InvitationStatus::ACCEPTED;
    }

    public function isRejected(): bool
    {
        return $this->statusEnum() === InvitationStatus::REJECTED;
    }

    public function isCanceled(): bool
    {
        return $this->statusEnum() === InvitationStatus::CANCELED;
    }

    public function canBeAccepted(): bool
    {
        return $this->isPending() && ! $this->isExpired();
    }

    public function markAsExpired(): void
    {
        $this->update(['status' => InvitationStatus::EXPIRED->value]);
    }

    public function statusEnum(): InvitationStatus
    {
        $status = $this->getAttributeValue('status');

        if ($status instanceof InvitationStatus) {
            return $status;
        }

        return InvitationStatus::from($status ?? InvitationStatus::PENDING->value);
    }
}
