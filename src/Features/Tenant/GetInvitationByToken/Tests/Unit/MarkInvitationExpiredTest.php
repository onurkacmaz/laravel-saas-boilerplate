<?php

use App\Models\Invitation;
use Features\Tenant\Shared\Enums\InvitationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('marks invitation as expired', function (): void {
    $invitation = Invitation::factory()->pending()->create();
    $invitation->markAsExpired();

    expect($invitation->fresh()->statusEnum())->toBe(InvitationStatus::EXPIRED);
});
