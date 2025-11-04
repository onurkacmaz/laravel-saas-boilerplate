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
