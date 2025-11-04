<?php

use App\Models\Invitation;
use App\Models\Organization;
use Features\Tenant\SendInvitation\Notifications\OrganizationInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();
});

it('sends invitation notification with correct subject', function (): void {
    $organization = Organization::factory()->create();
    $invitation = Invitation::factory()->create([
        'organization_id' => $organization->id,
    ]);

    Notification::route('mail', $invitation->email)
        ->notify(new OrganizationInvitation($invitation));

    Notification::assertSentOnDemand(OrganizationInvitation::class, function ($notification, $channels, $notifiable) use ($invitation) {
        $mailMessage = $notification->toMail($notifiable);

        return $mailMessage->subject === "You're invited to join {$invitation->organization->name}";
    });
});
