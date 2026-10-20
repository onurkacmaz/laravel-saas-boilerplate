<?php

use App\Models\User;
use Features\Auth\TwoFactorAuth\Infrastructure\Support\TwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function twoFactorUser(): User
{
    $user = User::factory()->create([
        'email' => 'secure@example.com',
        'password' => 'Password123!',
    ]);

    $user->forceFill([
        'two_factor_enabled' => true,
        'two_factor_verified' => true,
        'two_factor_secret' => (new TwoFactor)->generateTwoFactorSecret(),
    ])->save();

    $user->regenerateRecoveryCodes();

    return $user->fresh();
}

it('accepts codes from adjacent time steps and rejects others', function (): void {
    $service = new TwoFactor;
    $secret = $service->generateTwoFactorSecret();

    expect($service->verifyOtp($secret, $service->generateOtp($secret, 30, 6, time() - 30)))->toBeTrue()
        ->and($service->verifyOtp($secret, $service->generateOtp($secret, 30, 6, time() + 30)))->toBeTrue()
        ->and($service->verifyOtp($secret, $service->generateOtp($secret, 30, 6, time() - 300)))->toBeFalse()
        ->and($service->verifyOtp($secret, 'abcdef'))->toBeFalse()
        ->and($service->verifyOtp($secret, ''))->toBeFalse();
});

it('matches the RFC 6238 test vector', function (): void {
    // Secret "12345678901234567890" (ASCII) in base32, T = 59s => 287082 (SHA1, 6 digits).
    expect((new TwoFactor)->generateOtp('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 30, 6, 59))->toBe('287082');
});

it('requires a second factor before issuing an API token', function (): void {
    twoFactorUser();

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'secure@example.com',
        'password' => 'Password123!',
    ]);

    $response->assertStatus(202)
        ->assertJsonPath('data.two_factor_required', true);

    expect($response->json('data.token'))->not->toBeEmpty();
});

it('completes the login with a valid code and refuses to replay it', function (): void {
    $user = twoFactorUser();

    $jwt = $this->postJson('/api/v1/auth/login', [
        'email' => 'secure@example.com',
        'password' => 'Password123!',
    ])->json('data.token');

    $code = (new TwoFactor)->generateOtp($user->two_factor_secret);

    $this->postJson('/api/v1/2fa/challenge', ['jwt' => $jwt, 'code' => $code])
        ->assertOk()
        ->assertJsonStructure(['data' => ['token']]);

    $this->postJson('/api/v1/2fa/challenge', ['jwt' => $jwt, 'code' => $code])
        ->assertUnauthorized();

    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('consumes recovery codes', function (): void {
    $user = twoFactorUser();
    $recoveryCode = $user->recoveryCodes()[0];

    $jwt = $user->createJwtToken();

    $this->postJson('/api/v1/2fa/challenge', ['jwt' => $jwt, 'recovery_code' => $recoveryCode])
        ->assertOk();

    expect($user->fresh()->recoveryCodes())->not->toContain($recoveryCode);

    $this->postJson('/api/v1/2fa/challenge', ['jwt' => $jwt, 'recovery_code' => $recoveryCode])
        ->assertUnauthorized();
});

it('rejects tampered or foreign challenge tokens', function (): void {
    twoFactorUser();

    $this->postJson('/api/v1/2fa/challenge', ['jwt' => 'not-a-jwt', 'code' => '123456'])
        ->assertUnauthorized();

    $this->postJson('/api/v1/2fa/challenge', ['jwt' => 'not-a-jwt'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code', 'recovery_code']);
});

it('does not verify two-factor authentication with an invalid code', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')->postJson('/api/v1/2fa/enable')->assertStatus(202);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/2fa/confirm', ['code' => '000000'])
        ->assertUnprocessable();

    $user->refresh();

    expect($user->two_factor_verified)->toBeFalse()
        ->and($user->recoveryCodes())->toBe([]);

    $code = (new TwoFactor)->generateOtp($user->two_factor_secret);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/2fa/confirm', ['code' => $code])
        ->assertOk()
        ->assertJsonCount(8, 'data.recovery_codes');

    expect($user->fresh()->hasTwoFactorEnabled())->toBeTrue();
});

it('requires the current password to disable two-factor authentication', function (): void {
    $user = twoFactorUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/2fa/disable', ['password' => 'wrong-password'])
        ->assertUnprocessable();

    expect($user->fresh()->hasTwoFactorEnabled())->toBeTrue();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/2fa/disable', ['password' => 'Password123!'])
        ->assertOk();

    expect($user->fresh()->hasTwoFactorEnabled())->toBeFalse();
});
