<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

it('treats e-mail addresses case-insensitively', function (): void {
    User::factory()->create(['email' => 'Mixed.Case@Example.com', 'password' => 'Password123!']);

    expect(User::query()->where('email', 'mixed.case@example.com')->exists())->toBeTrue();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'MIXED.case@example.COM',
        'password' => 'Password123!',
    ])->assertOk();

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Duplicate',
        'email' => 'MIXED.CASE@EXAMPLE.COM',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
});

it('throttles repeated login attempts', function (): void {
    User::factory()->create(['email' => 'victim@example.com']);

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/login', ['email' => 'victim@example.com', 'password' => 'wrong-password'])
            ->assertUnauthorized();
    }

    $this->postJson('/api/v1/auth/login', ['email' => 'victim@example.com', 'password' => 'wrong-password'])
        ->assertTooManyRequests();
});

it('registers a user with a personal organization and sends a verification e-mail', function (): void {
    Notification::fake();

    $this->postJson('/api/v1/auth/register', [
        'name' => 'New User',
        'email' => 'new@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertCreated();

    $user = User::query()->where('email', 'new@example.com')->firstOrFail();

    expect($user->ownedOrganizations()->count())->toBe(1)
        ->and($user->organizations()->count())->toBe(1);

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('verifies e-mail addresses through a signed link', function (): void {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('api.v1.auth.verification.verify', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);

    $this->getJson(str_replace(sha1($user->email), sha1('someone-else@example.com'), $url))->assertForbidden();

    $this->getJson($url)->assertOk();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('logs out by revoking the current token', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('auth_token')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

    expect($user->tokens()->count())->toBe(0);
});

it('does not reveal whether an account exists when requesting a reset link', function (): void {
    Notification::fake();

    $user = User::factory()->create(['email' => 'known@example.com']);

    $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'known@example.com'])->assertOk();
    $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'unknown@example.com'])->assertOk();

    expect($known->json('message'))->toBe($unknown->json('message'));

    Notification::assertSentTo($user, ResetPassword::class);
});

it('resets the password and revokes existing tokens', function (): void {
    $user = User::factory()->create(['email' => 'reset@example.com']);
    $user->createToken('old');
    $token = Password::broker()->createToken($user);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => 'invalid',
        'email' => 'reset@example.com',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ])->assertUnprocessable();

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => 'reset@example.com',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ])->assertOk();

    expect($user->tokens()->count())->toBe(0);

    $this->postJson('/api/v1/auth/login', ['email' => 'reset@example.com', 'password' => 'NewPassword123!'])
        ->assertOk();
});

it('changes the password only with the current password', function (): void {
    $user = User::factory()->create(['password' => 'Password123!']);

    $this->actingAs($user, 'sanctum')->putJson('/api/v1/auth/password', [
        'current_password' => 'wrong',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ])->assertUnprocessable();

    $this->actingAs($user, 'sanctum')->putJson('/api/v1/auth/password', [
        'current_password' => 'Password123!',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ])->assertOk();
});
