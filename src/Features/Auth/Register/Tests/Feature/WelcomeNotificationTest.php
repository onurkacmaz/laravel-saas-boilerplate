<?php

use App\Models\User;
use Features\Auth\Register\Notifications\WelcomeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();
});

it('sends welcome notification when a user registers', function (): void {
    $user = User::factory()->create();

    $user->notify(new WelcomeNotification($user));

    Notification::assertSentTo($user, WelcomeNotification::class);
});

it('links to the documentation only when DOCS_URL is set', function (): void {
    config(['app.frontend_url' => 'https://app.example.com/']);
    $user = User::factory()->create();

    config(['app.docs_url' => null]);
    $mail = (new WelcomeNotification($user))->toMail($user);

    expect($mail->actionUrl)->toBe('https://app.example.com/dashboard')
        ->and(implode(' ', $mail->outroLines))->not->toContain('documentation');

    config(['app.docs_url' => 'https://docs.example.com']);
    $mail = (new WelcomeNotification($user))->toMail($user);

    expect(implode(' ', $mail->outroLines))->toContain('[documentation](https://docs.example.com)');
});
