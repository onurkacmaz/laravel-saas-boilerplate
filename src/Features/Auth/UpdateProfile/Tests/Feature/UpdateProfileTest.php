<?php

use App\Models\User;
use Features\Auth\UpdateProfile\Notifications\EmailChangedNotification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->user = User::factory()->create([
        'name' => 'Ayşe Demir',
        'email' => 'ayse@example.com',
        'password' => 'secret-password',
    ]);
});

it('changes the name', function () {
    $this->actingAs($this->user, 'sanctum')
        ->putJson(route('api.v1.auth.profile.update'), ['name' => '  Ayşe Yılmaz  '])
        ->assertOk()
        ->assertJsonPath('data.name', 'Ayşe Yılmaz')
        ->assertJsonPath('message', 'Profile updated.');

    expect($this->user->fresh())
        ->name->toBe('Ayşe Yılmaz')
        ->email_verified_at->not->toBeNull();
    Notification::assertNothingSent();
});

it('changes the e-mail address with the current password and asks to verify it', function () {
    $this->actingAs($this->user, 'sanctum')
        ->putJson(route('api.v1.auth.profile.update'), [
            'email' => 'Ayse.Demir@Example.com',
            'current_password' => 'secret-password',
        ])
        ->assertOk()
        ->assertJsonPath('data.email', 'ayse.demir@example.com')
        ->assertJsonPath('data.email_verified_at', null);

    $user = $this->user->fresh();
    expect($user->email)->toBe('ayse.demir@example.com')
        ->and($user->hasVerifiedEmail())->toBeFalse();

    Notification::assertSentTo($user, VerifyEmail::class);
    Notification::assertSentOnDemand(
        EmailChangedNotification::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'ayse@example.com'
            && $notification->newEmail === 'ayse.demir@example.com',
    );
});

it('needs the current password to change the e-mail address', function () {
    $this->actingAs($this->user, 'sanctum')
        ->putJson(route('api.v1.auth.profile.update'), ['email' => 'new@example.com'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('current_password');

    $this->actingAs($this->user, 'sanctum')
        ->putJson(route('api.v1.auth.profile.update'), ['email' => 'new@example.com', 'current_password' => 'wrong'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('current_password');

    expect($this->user->fresh()->email)->toBe('ayse@example.com');
    Notification::assertNothingSent();
});

it('does not need the password when the e-mail address stays the same', function () {
    $this->actingAs($this->user, 'sanctum')
        ->putJson(route('api.v1.auth.profile.update'), ['name' => 'Ayşe', 'email' => 'AYSE@example.com'])
        ->assertOk();

    expect($this->user->fresh()->hasVerifiedEmail())->toBeTrue();
    Notification::assertNothingSent();
});

it('rejects an e-mail address another account uses', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->actingAs($this->user, 'sanctum')
        ->putJson(route('api.v1.auth.profile.update'), [
            'email' => 'Taken@example.com',
            'current_password' => 'secret-password',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('rejects an empty name', function () {
    $this->actingAs($this->user, 'sanctum')
        ->putJson(route('api.v1.auth.profile.update'), ['name' => '   '])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

it('requires authentication', function () {
    $this->putJson(route('api.v1.auth.profile.update'), ['name' => 'Someone'])->assertUnauthorized();
});

it('describes the e-mail change to the previous address', function () {
    $mail = (new EmailChangedNotification('Ayşe Demir', 'new@example.com'))->toMail(new stdClass);

    expect($mail->subject)->toBe('Your e-mail address was changed')
        ->and(implode(' ', $mail->introLines))->toContain('new@example.com');
});
