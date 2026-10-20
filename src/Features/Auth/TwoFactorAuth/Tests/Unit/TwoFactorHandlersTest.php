<?php

use App\Models\User;
use Features\Auth\TwoFactorAuth\Application\ConfirmTwoFactor;
use Features\Auth\TwoFactorAuth\Application\ConfirmTwoFactorHandler;
use Features\Auth\TwoFactorAuth\Application\DisableTwoFactor;
use Features\Auth\TwoFactorAuth\Application\DisableTwoFactorHandler;
use Features\Auth\TwoFactorAuth\Application\EnableTwoFactor;
use Features\Auth\TwoFactorAuth\Application\EnableTwoFactorHandler;
use Features\Auth\TwoFactorAuth\Events\InvalidTwoFactorCode;
use Features\Auth\TwoFactorAuth\Events\TwoFactorConfirmed;
use Features\Auth\TwoFactorAuth\Events\TwoFactorDisabled;
use Features\Auth\TwoFactorAuth\Events\TwoFactorEnabled;
use Features\Auth\TwoFactorAuth\Infrastructure\Support\TwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->fakeTwoFactor = new class extends TwoFactor
    {
        public bool $shouldPass = true;

        public string $secret = 'JBSWY3DPEHPK3PXP';

        public function generateTwoFactorSecret(): string
        {
            return $this->secret;
        }

        public function verifyOtp(string $secret, string $otp, int $timeStep = 30, int $window = 1): bool
        {
            return $this->shouldPass && $secret === $this->secret && $otp === '123456';
        }
    };

    app()->instance(TwoFactor::class, $this->fakeTwoFactor);
    Event::fake();
});

it('enables two factor authentication for a user', function (): void {
    $user = User::factory()->create([
        'two_factor_enabled' => false,
        'two_factor_secret' => null,
    ]);

    $handler = app(EnableTwoFactorHandler::class);

    $data = $handler(new EnableTwoFactor($user));

    expect($data['secret'])->toBe($this->fakeTwoFactor->secret);
    expect($data['totp_uri'])->toContain($this->fakeTwoFactor->secret);

    $user->refresh();
    expect((bool) $user->two_factor_enabled)->toBeTrue();
    expect($user->two_factor_secret)->toBe($this->fakeTwoFactor->secret);

    Event::assertDispatched(TwoFactorEnabled::class);
});

it('confirms two factor with a valid code and generates recovery codes', function (): void {
    $user = User::factory()->create([
        'two_factor_enabled' => true,
        'two_factor_secret' => $this->fakeTwoFactor->secret,
    ]);

    $handler = app(ConfirmTwoFactorHandler::class);
    $result = $handler(new ConfirmTwoFactor($user, '123456'));

    expect($result)->toBeTrue();

    $user->refresh();
    expect((bool) $user->two_factor_verified)->toBeTrue();
    expect($user->recoveryCodes())->toHaveCount(8);

    Event::assertDispatched(TwoFactorConfirmed::class);
    Event::assertNotDispatched(InvalidTwoFactorCode::class);
});

it('dispatches invalid code event when confirmation fails', function (): void {
    $this->fakeTwoFactor->shouldPass = false;

    $user = User::factory()->create([
        'two_factor_enabled' => true,
        'two_factor_secret' => $this->fakeTwoFactor->secret,
    ]);

    $handler = app(ConfirmTwoFactorHandler::class);
    $result = $handler(new ConfirmTwoFactor($user, '000000'));

    expect($result)->toBeFalse();
    Event::assertDispatched(InvalidTwoFactorCode::class);
});

it('disables two factor authentication and clears secrets', function (): void {
    $user = User::factory()->create([
        'two_factor_enabled' => true,
        'two_factor_secret' => $this->fakeTwoFactor->secret,
        'two_factor_verified' => true,
        'two_factor_recovery_codes' => '["foo"]',
    ]);

    $handler = app(DisableTwoFactorHandler::class);
    $handler(new DisableTwoFactor($user));

    $user->refresh();
    expect((bool) $user->two_factor_enabled)->toBeFalse();
    expect($user->two_factor_secret)->toBeNull();
    expect((bool) $user->two_factor_verified)->toBeFalse();
    expect($user->two_factor_recovery_codes)->toBeNull();

    Event::assertDispatched(TwoFactorDisabled::class);
});

it('generates and verifies otp codes using the two factor utility', function (): void {
    $service = new TwoFactor;
    $secret = $service->generateTwoFactorSecret();

    expect($secret)->toHaveLength(32);

    $otp = $service->generateOtp($secret);
    expect($otp)->toHaveLength(6);

    expect($service->verifyOtp($secret, $otp))->toBeTrue();
    expect($service->verifyOtp($secret, '000000'))->toBeFalse();
});
