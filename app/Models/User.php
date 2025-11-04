<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\UserFactory;
use Features\Audit\Infrastructure\Eloquent\Models\AuditLog;
use Firebase\JWT\JWT;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use JsonException;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property string $token
 * @property bool $two_factor_enabled
 * @property string $two_factor_secret
 * @property string $email
 * @property bool $two_factor_verified
 * @property string $two_factor_recovery_codes
 * @property string $id
 * @property string $name
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, \Illuminate\Auth\MustVerifyEmail, Notifiable, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_enabled',
        'two_factor_verified',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_user')
            ->withPivot('role_id')
            ->withTimestamps();
    }

    public function assignDefaultRole(?Role $role = null): void
    {
        if (is_null($role)) {
            $role = Role::query()->where('is_default', true)->first();
        }

        if (is_null($role)) {
            return;
        }

        $this->roles()->syncWithoutDetaching([$role->id]);
    }

    public function detachRole(Role $role): void
    {
        $this->roles()->detach($role);
    }

    /**
     * @throws JsonException
     */
    public function recoveryCodes(): array
    {
        return collect(json_decode($this->two_factor_recovery_codes, true, 512, JSON_THROW_ON_ERROR))
            ->map(fn ($code) => decrypt($code))
            ->toArray();
    }

    public function createJwtToken(): string
    {
        return JWT::encode(
            payload: $this->jwtPayload(),
            key: env('JWT_SECRET'),
            alg: 'HS256'
        );
    }

    public function jwtPayload(): array
    {
        $now = Carbon::now();

        return [
            'iss' => config('app.url'),
            'iat' => $now->timestamp,
            'nbf' => $now->timestamp,
            'exp' => $now->addMinutes(5)->timestamp,
            'sub' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }

    /**
     * @throws JsonException
     */
    public function replaceRecoveryCode(string $code): void
    {
        $recoveryCodes = $this->recoveryCodes();

        if (! in_array($code, $recoveryCodes, true)) {
            return;
        }

        $recoveryCodes[array_search($code, $recoveryCodes, true)] = Str::random(10).'-'.Str::random(10);

        $this->update([
            'two_factor_recovery_codes' => json_encode(array_map(static fn ($code) => encrypt($code), $recoveryCodes), JSON_THROW_ON_ERROR),
        ]);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function ownedOrganizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'owner_id');
    }

    #[Scope]
    public function scopeCoreAdmins($query)
    {
        return $query->where('is_core_admin', true);
    }
}
