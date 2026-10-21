<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\UserFactory;
use Features\Audit\Infrastructure\Eloquent\Models\AuditLog;
use Firebase\JWT\JWT;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use JsonException;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property bool $two_factor_enabled
 * @property string|null $two_factor_secret
 * @property bool $two_factor_verified
 * @property string|null $two_factor_recovery_codes
 * @property bool $is_core_admin
 * @property Carbon|null $last_login_at
 * @property Carbon|null $deletion_requested_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property string|null $locale
 */
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, \Illuminate\Auth\MustVerifyEmail, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * Security-sensitive columns (two-factor secrets, admin flags, timestamps
     * driven by the system) are intentionally not mass assignable; they are
     * written with forceFill() by the code paths that own them.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'locale',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
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
            'two_factor_enabled' => 'boolean',
            'two_factor_verified' => 'boolean',
            'is_core_admin' => 'boolean',
            'last_login_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
        ];
    }

    /**
     * The language of the e-mails sent to this user.
     */
    public function preferredLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * E-mail addresses are compared case-insensitively everywhere, so they are
     * always stored trimmed and lower-cased.
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value === null ? null : self::normalizeEmail($value),
        );
    }

    public static function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_enabled
            && $this->two_factor_verified
            && ! empty($this->two_factor_secret);
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
            // Only system-wide roles may be used as the default for new users;
            // a tenant's own "default" role must never leak to other tenants.
            $role = Role::query()
                ->withoutOrganization()
                ->whereNull('organization_id')
                ->where('is_default', true)
                ->first();
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
        if (empty($this->two_factor_recovery_codes)) {
            return [];
        }

        return collect(json_decode($this->two_factor_recovery_codes, true, 512, JSON_THROW_ON_ERROR))
            ->map(fn ($code) => decrypt($code))
            ->values()
            ->toArray();
    }

    /**
     * Generate and store a fresh set of recovery codes, returning them in plain text.
     *
     * @return list<string>
     *
     * @throws JsonException
     */
    public function regenerateRecoveryCodes(int $count = 8): array
    {
        $codes = collect(range(1, $count))
            ->map(fn () => Str::random(10).'-'.Str::random(10))
            ->all();

        $this->forceFill([
            'two_factor_recovery_codes' => json_encode(
                array_map(static fn ($code) => encrypt($code), $codes),
                JSON_THROW_ON_ERROR
            ),
        ])->save();

        return $codes;
    }

    public function createJwtToken(): string
    {
        return JWT::encode(
            payload: $this->jwtPayload(),
            key: self::jwtSecret(),
            alg: 'HS256'
        );
    }

    public static function jwtSecret(): string
    {
        $secret = (string) config('auth.jwt.secret');

        if ($secret === '') {
            $secret = (string) config('app.key');
        }

        if (str_starts_with($secret, 'base64:')) {
            $secret = base64_decode(substr($secret, 7)) ?: $secret;
        }

        // HS256 requires a key of at least 256 bits; derive one deterministically
        // from whatever was configured so short secrets keep working safely.
        return strlen($secret) >= 32 ? $secret : hash('sha256', $secret, true);
    }

    public function jwtPayload(): array
    {
        $now = Carbon::now();

        return [
            'iss' => config('app.url'),
            'iat' => $now->timestamp,
            'nbf' => $now->timestamp,
            'exp' => $now->copy()->addMinutes((int) config('auth.jwt.ttl', 5))->timestamp,
            'sub' => $this->id,
            'purpose' => '2fa',
            'name' => $this->name,
            'email' => $this->email,
        ];
    }

    /**
     * Consume a recovery code: it is replaced by a new random code so that it
     * cannot be used twice.
     *
     * @throws JsonException
     */
    public function replaceRecoveryCode(string $code): void
    {
        $recoveryCodes = $this->recoveryCodes();
        $index = array_search($code, $recoveryCodes, true);

        if ($index === false) {
            return;
        }

        $recoveryCodes[$index] = Str::random(10).'-'.Str::random(10);

        $this->forceFill([
            'two_factor_recovery_codes' => json_encode(array_map(static fn ($code) => encrypt($code), $recoveryCodes), JSON_THROW_ON_ERROR),
        ])->save();
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function ownedOrganizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'owner_id');
    }

    public function scopeCoreAdmins(Builder $query): Builder
    {
        return $query->where('is_core_admin', true);
    }
}
