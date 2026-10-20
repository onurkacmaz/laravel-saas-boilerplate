<?php

use App\Models\User;
use Features\Auth\Register\Events\UserRegisterSucceeded;
use Features\Auth\Register\Notifications\FailedRegistrationNotification;
use Features\Auth\Register\Notifications\WelcomeNotification;
use Features\Auth\TwoFactorAuth\Infrastructure\Support\TwoFactor;
use Features\Auth\TwoFactorAuth\Notifications\TwoFactorDisabledNotification;
use Features\Auth\TwoFactorAuth\Notifications\TwoFactorEnabledNotification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

function enableTwoFactorFor(User $user): User
{
    $user->forceFill([
        'two_factor_enabled' => true,
        'two_factor_verified' => true,
        'two_factor_secret' => (new TwoFactor)->generateTwoFactorSecret(),
    ])->save();
    $user->regenerateRecoveryCodes();

    return $user->fresh();
}

it('resends the verification e-mail only to unverified users', function (): void {
    Notification::fake();

    $unverified = User::factory()->unverified()->create();
    $verified = User::factory()->create();

    $this->actingAs($unverified, 'sanctum')
        ->postJson('/api/v1/auth/email/verification-notification')
        ->assertStatus(202);

    $this->actingAs($verified, 'sanctum')
        ->postJson('/api/v1/auth/email/verification-notification')
        ->assertOk()
        ->assertJsonPath('message', 'E-mail address already verified.');

    Notification::assertSentTo($unverified, VerifyEmail::class);
    Notification::assertNotSentTo($verified, VerifyEmail::class);
});

it('rejects verification links for unknown users and accepts repeated clicks', function (): void {
    $unknown = URL::temporarySignedRoute('api.v1.auth.verification.verify', now()->addHour(), [
        'id' => '99999999-9999-4999-9999-999999999999',
        'hash' => sha1('ghost@example.com'),
    ]);

    $this->getJson($unknown)->assertForbidden();

    $user = User::factory()->create();
    $url = URL::temporarySignedRoute('api.v1.auth.verification.verify', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);

    $this->getJson($url)->assertOk();
    $this->getJson($url)->assertOk();
});

it('rolls back the registration and alerts platform admins when it fails', function (): void {
    Notification::fake();
    $admin = User::factory()->create();
    $admin->forceFill(['is_core_admin' => true])->save();

    Event::listen(UserRegisterSucceeded::class, function () {
        throw new RuntimeException('Mail server unreachable');
    });

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Unlucky',
        'email' => 'unlucky@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertServerError();

    expect(User::query()->where('email', 'unlucky@example.com')->exists())->toBeFalse();

    Notification::assertSentTo($admin, FailedRegistrationNotification::class, function (FailedRegistrationNotification $notification) use ($admin) {
        $mail = $notification->toMail($admin);

        return $notification->email === 'unlucky@example.com'
            && $notification->via($admin) === ['mail']
            && str_contains(implode(' ', $mail->introLines), 'Mail server unreachable')
            && $notification->toArray($admin)['reason'] === 'Mail server unreachable';
    });
});

it('renders the account notifications', function (): void {
    config(['app.frontend_url' => 'https://app.example.test']);
    $user = User::factory()->create(['name' => 'Ada']);

    $welcome = new WelcomeNotification($user);
    $enabled = new TwoFactorEnabledNotification($user);
    $disabled = new TwoFactorDisabledNotification($user);

    expect($welcome->toMail($user)->greeting)->toBe('Hello Ada!')
        ->and($welcome->toMail($user)->actionUrl)->toBe('https://app.example.test/dashboard')
        ->and($welcome->toArray($user))->toMatchArray(['user_id' => $user->id, 'user_email' => $user->email])
        ->and($enabled->toMail($user)->subject)->toBe('Two-Factor Authentication Enabled')
        ->and($enabled->toArray($user)['user_id'])->toBe($user->id)
        ->and($disabled->toMail($user)->subject)->toBe('Two-Factor Authentication Disabled')
        ->and($disabled->toMail($user)->actionUrl)->toBe('https://app.example.test/settings/security')
        ->and($disabled->toArray($user)['user_id'])->toBe($user->id);
});

it('manages recovery codes only when two-factor authentication is active', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/2fa/recovery-codes')->assertStatus(400);
    $this->actingAs($user, 'sanctum')->postJson('/api/v1/2fa/recovery-codes')->assertStatus(400);

    $user = enableTwoFactorFor($user);
    $old = $user->recoveryCodes();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/2fa/recovery-codes')
        ->assertOk()
        ->assertJsonPath('data', $old);

    $new = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/2fa/recovery-codes')
        ->assertOk()
        ->json('data');

    expect($new)->toHaveCount(8)
        ->and(array_intersect($new, $old))->toBe([])
        ->and($user->fresh()->recoveryCodes())->toBe($new);
});

it('refuses to re-enroll while two-factor authentication is active', function (): void {
    $user = enableTwoFactorFor(User::factory()->create());

    $this->actingAs($user, 'sanctum')->postJson('/api/v1/2fa/enable')->assertStatus(409);
});

it('refuses to confirm when enrollment was never started', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')->postJson('/api/v1/2fa/confirm', ['code' => '123456'])->assertStatus(400);
});

it('rejects a challenge when two-factor authentication was disabled meanwhile', function (): void {
    $user = enableTwoFactorFor(User::factory()->create());
    $jwt = $user->createJwtToken();

    $user->forceFill(['two_factor_enabled' => false, 'two_factor_verified' => false, 'two_factor_secret' => null])->save();

    $this->postJson('/api/v1/2fa/challenge', ['jwt' => $jwt, 'code' => '123456'])->assertUnauthorized();
});

it('rejects an invalid TOTP code in the challenge', function (): void {
    $user = enableTwoFactorFor(User::factory()->create());

    $this->postJson('/api/v1/2fa/challenge', ['jwt' => $user->createJwtToken(), 'code' => '000000'])->assertUnauthorized();
    $this->postJson('/api/v1/2fa/challenge', ['jwt' => $user->createJwtToken(), 'recovery_code' => 'not-a-code'])->assertUnauthorized();
});

it('logs out from every device', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('laptop')->plainTextToken;
    $user->createToken('phone');

    $this->withToken($token)->postJson('/api/v1/auth/logout', ['all_devices' => true])->assertOk();

    expect($user->tokens()->count())->toBe(0);
});

it('keeps the current token when changing the password', function (): void {
    $user = User::factory()->create(['password' => 'Password123!']);
    $current = $user->createToken('current')->plainTextToken;
    $user->createToken('other');

    $this->withToken($current)->putJson('/api/v1/auth/password', [
        'current_password' => 'Password123!',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ])->assertOk();

    expect($user->tokens()->pluck('name')->all())->toBe(['current']);
});

it('derives a strong signing key from short JWT secrets', function (): void {
    config(['auth.jwt.secret' => 'short']);
    expect(strlen(User::jwtSecret()))->toBe(32);

    config(['auth.jwt.secret' => '', 'app.key' => 'base64:'.base64_encode(str_repeat('k', 32))]);
    expect(User::jwtSecret())->toBe(str_repeat('k', 32));
});

it('only assigns system default roles to new users', function (): void {
    $tenantDefault = \App\Models\Role::factory()->create([
        'organization_id' => \App\Models\Organization::factory()->create()->id,
        'is_default' => true,
    ]);
    $systemDefault = \Features\Acl\Shared\Support\SystemRoles::ensure('member');

    $user = User::factory()->create();
    $user->assignDefaultRole();

    expect($user->roles()->pluck('roles.id')->all())->toBe([$systemDefault->id])
        ->and($user->roles()->pluck('roles.id')->all())->not->toContain($tenantDefault->id);

    $user->detachRole($systemDefault);
    expect($user->roles()->count())->toBe(0);
});

it('rejects expired challenge tokens', function (): void {
    $user = enableTwoFactorFor(User::factory()->create());
    $jwt = $user->createJwtToken();

    $this->travel(6)->minutes();

    $this->postJson('/api/v1/2fa/challenge', [
        'jwt' => $jwt,
        'code' => (new TwoFactor)->generateOtp($user->two_factor_secret),
    ])->assertUnauthorized();
});
