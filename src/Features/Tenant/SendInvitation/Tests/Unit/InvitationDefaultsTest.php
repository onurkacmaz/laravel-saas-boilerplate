<?php

use App\Models\Invitation;
use Features\Tenant\Shared\Enums\InvitationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('assigns default attributes when creating an invitation', function (): void {
    Carbon::setTestNow('2025-02-01 12:00:00');

    $invitation = Invitation::factory()->create([
        'token' => null,
        'expires_at' => null,
        'status' => null,
    ]);

    expect($invitation->token)->not->toBeNull();
    expect($invitation->expires_at)->toEqual(now()->addDays(7));
    expect($invitation->statusEnum())->toBe(InvitationStatus::PENDING);

    Carbon::setTestNow();
});
