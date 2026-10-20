<?php

use App\Models\User;
use Features\Auth\TwoFactorAuth\Infrastructure\Support\TwoFactor;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

/**
 * Enable 2FA → log in with a code → log in with a recovery code →
 * regenerate codes → disable 2FA.
 */
it('runs the full two-factor authentication lifecycle', function (): void {
    Notification::fake();
    User::factory()->create(['email' => 'secure@example.com', 'password' => 'Password123!']);
    $totp = new TwoFactor;

    $login = fn () => actingWithToken(null)->postJson('/api/v1/auth/login', ['email' => 'secure@example.com', 'password' => 'Password123!']);
    $challengeWith = fn (array $body) => actingWithToken(null)->postJson('/api/v1/2fa/challenge', $body);
    $token = $login()->assertOk()->json('data.token');

    // Enrol: the secret is only active once a code from the app confirms it.
    $secret = actingWithToken($token)->postJson('/api/v1/2fa/enable')->assertStatus(202)->json('data.secret');
    actingWithToken($token)->postJson('/api/v1/2fa/confirm', ['code' => '000000'])->assertUnprocessable();

    $recoveryCodes = actingWithToken($token)
        ->postJson('/api/v1/2fa/confirm', ['code' => $totp->generateOtp($secret)])
        ->assertOk()
        ->json('data.recovery_codes');

    expect($recoveryCodes)->toHaveCount(8);

    actingWithToken($token)->postJson('/api/v1/auth/logout')->assertOk();
    $this->app['auth']->forgetGuards();

    // Login now stops at the second factor.
    $challenge = $login()->assertStatus(202)->json('data.token');

    $apiToken = $challengeWith(['jwt' => $challenge, 'code' => $totp->generateOtp($secret)])
        ->assertOk()
        ->json('data.token');

    actingWithToken($apiToken)->getJson('/api/v1/user')->assertOk()->assertJsonPath('data.two_factor_enabled', true);

    // Lost phone: a recovery code works exactly once.
    $recoveryToken = $challengeWith(['jwt' => $login()->json('data.token'), 'recovery_code' => $recoveryCodes[0]])
        ->assertOk()
        ->json('data.token');

    $challengeWith(['jwt' => $login()->json('data.token'), 'recovery_code' => $recoveryCodes[0]])
        ->assertUnauthorized();

    // Fresh recovery codes invalidate the previous ones.
    $newCodes = actingWithToken($recoveryToken)->postJson('/api/v1/2fa/recovery-codes')->assertOk()->json('data');

    // Logins are throttled per e-mail address; let the window pass.
    $this->travel(2)->minutes();

    $challengeWith(['jwt' => $login()->json('data.token'), 'recovery_code' => $recoveryCodes[1]])
        ->assertUnauthorized();
    $challengeWith(['jwt' => $login()->json('data.token'), 'recovery_code' => $newCodes[0]])
        ->assertOk();

    // Disabling requires the password; afterwards login is single-step again.
    actingWithToken($recoveryToken)->postJson('/api/v1/2fa/disable', ['password' => 'Password123!'])->assertOk();

    $login()->assertOk()->assertJsonPath('data.two_factor_required', false);
});

/**
 * Forgot password → reset with the e-mailed token → old sessions are gone.
 */
it('recovers an account through a password reset', function (): void {
    Notification::fake();
    $user = User::factory()->create(['email' => 'forgetful@example.com', 'password' => 'OldPassword123!']);

    $oldToken = $this->postJson('/api/v1/auth/login', ['email' => 'forgetful@example.com', 'password' => 'OldPassword123!'])
        ->json('data.token');

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'FORGETFUL@example.com'])->assertOk();

    $resetToken = null;
    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$resetToken) {
        $resetToken = $notification->token;

        return true;
    });

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $resetToken,
        'email' => 'forgetful@example.com',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ])->assertOk();

    actingWithToken($oldToken)->getJson('/api/v1/user')->assertUnauthorized();
    actingWithToken(null);

    // The reset token is single-use.
    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $resetToken,
        'email' => 'forgetful@example.com',
        'password' => 'Another123!',
        'password_confirmation' => 'Another123!',
    ])->assertUnprocessable();

    // The auth endpoints share a per-e-mail throttle; let the window pass.
    $this->travel(2)->minutes();

    $this->postJson('/api/v1/auth/login', ['email' => 'forgetful@example.com', 'password' => 'OldPassword123!'])->assertUnauthorized();
    $this->postJson('/api/v1/auth/login', ['email' => 'forgetful@example.com', 'password' => 'NewPassword123!'])->assertOk();
});
