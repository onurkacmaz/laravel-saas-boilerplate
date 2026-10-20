<?php

use App\Models\User;
use Features\Compliance\GdprDelete\Application\GdprDelete;
use Features\Compliance\GdprDelete\Application\GdprDeleteHandler;
use Features\Subscription\Infrastructure\Eloquent\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('downloads the export as a private JSON attachment', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->get('/api/v1/gdpr/download')->assertOk();

    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment; filename="user-data-'.$user->id)
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->json('personal_information.id'))->toBe($user->id);
});

it('keeps the original schedule when deletion is requested twice', function (): void {
    $user = User::factory()->create();
    $user->forceFill(['deletion_requested_at' => now()->subDays(10)])->save();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/gdpr/request-deletion')
        ->assertJsonPath('data.deletion_date', now()->addDays(20)->toDateString());
});

it('cancels the subscriptions of organizations that are deleted with the account', function (): void {
    [, $organization] = userInOrganization('member');
    $owner = $organization->owner;
    $subscription = Subscription::factory()->create([
        'organization_id' => $organization->id,
        'stripe_id' => Subscription::makeLocalStripeId(),
    ]);

    app(GdprDeleteHandler::class)(new GdprDelete($owner));

    expect($subscription->fresh()->stripe_status)->toBe('canceled');
});

it('reports failures of scheduled deletions', function (): void {
    $user = User::factory()->create();
    $user->forceFill(['deletion_requested_at' => now()->subDays(40)])->save();

    $this->mock(GdprDeleteHandler::class)->shouldReceive('__invoke')->andThrow(new RuntimeException('boom'));

    $this->artisan('gdpr:process-deletions')
        ->expectsOutputToContain("Failed to delete user {$user->id}: boom")
        ->assertFailed();
});

it('reports when there is nothing to delete', function (): void {
    $this->artisan('gdpr:process-deletions')
        ->expectsOutput('No users scheduled for deletion.')
        ->assertSuccessful();
});
