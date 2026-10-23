<?php

use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\PermissionRole;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Infrastructure\Eloquent\Models\Subscription;
use Features\Subscription\Subscribe\Application\SubscribeHandler;
use Features\Tenant\CancelInvitation\Application\CancelInvitation;
use Features\Tenant\CancelInvitation\Application\CancelInvitationHandler;
use Features\Webhooks\Infrastructure\Eloquent\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Infrastructure\Http\Pagination;
use Tests\Support\FakeStripeClient;

uses(RefreshDatabase::class);

it('exposes the pivot model relations', function (): void {
    [$user, $organization, $role] = userInOrganization('admin');
    $permission = Permission::factory()->create(['organization_id' => $organization->id]);
    $role->permissions()->attach($permission->id);
    $user->roles()->attach($role->id);

    $membership = OrganizationUser::query()->where('user_id', $user->id)->firstOrFail();
    $grant = new PermissionRole(['permission_id' => $permission->id, 'role_id' => $role->id]);
    $assignment = new UserRole(['user_id' => $user->id, 'role_id' => $role->id]);

    expect($membership->organization->is($organization))->toBeTrue()
        ->and($membership->user->is($user))->toBeTrue()
        ->and($membership->role->is($role))->toBeTrue()
        ->and($grant->permission->is($permission))->toBeTrue()
        ->and($grant->role->is($role))->toBeTrue()
        ->and($assignment->user->is($user))->toBeTrue()
        ->and($assignment->role->is($role))->toBeTrue()
        ->and($permission->roles()->first()->is($role))->toBeTrue()
        ->and($role->users()->first()->is($user))->toBeTrue();
});

it('answers organization membership questions', function (): void {
    [$member, $organization] = userInOrganization('member');
    $stranger = User::factory()->create();
    $plan = Plan::factory()->create();
    Subscription::factory()->create(['organization_id' => $organization->id, 'plan_id' => $plan->id]);
    Webhook::factory()->forOrganization($organization)->create();
    Webhook::factory()->forOrganization($organization)->inactive()->create();

    expect($organization->roleOf($stranger))->toBeNull()
        ->and($organization->isAdmin($member))->toBeFalse()
        ->and($organization->isAdmin($organization->owner))->toBeTrue()
        ->and($organization->subscribedToPlan($plan->id))->toBeTrue()
        ->and($organization->subscribedToPlan(Plan::factory()->create()->id))->toBeFalse()
        ->and($organization->activeWebhooks()->count())->toBe(1)
        ->and($organization->stripeEmail())->toBe($organization->owner->email);
});

it('marks subscriptions canceled locally when Stripe refuses to cancel them', function (): void {
    $stripe = FakeStripeClient::install();
    $stripe->failWith = 'Stripe is down';

    $organization = Organization::factory()->create(['stripe_id' => 'cus_1']);
    $subscription = Subscription::factory()->create(['organization_id' => $organization->id, 'stripe_id' => 'sub_remote']);

    $this->actingAs($organization->owner, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->deleteJson("/api/v1/organizations/{$organization->id}")
        ->assertNoContent();

    expect($subscription->fresh()->stripe_status)->toBe('canceled');
});

it('keeps the paid plan when Stripe cannot schedule the switch to a free plan', function (): void {
    $stripe = FakeStripeClient::install();
    [$admin, $organization] = userInOrganization('admin');
    $organization->forceFill(['stripe_id' => 'cus_1'])->save();
    $paid = Subscription::factory()->create(['organization_id' => $organization->id, 'stripe_id' => 'sub_remote']);
    $free = Plan::factory()->create(['price' => 0, 'stripe_price_id' => null]);
    $stripe->failWith = 'Stripe is down';

    // Canceling only locally would show the free plan while Stripe keeps charging.
    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->postJson('/api/v1/subscriptions', ['plan_id' => $free->id])
        ->assertStatus(502);

    expect($paid->fresh()->stripe_status)->toBe('active')
        ->and($paid->fresh()->ends_at)->toBeNull()
        ->and($organization->activeSubscription()->is($paid))->toBeTrue();
});

it('creates organizations even when the free plan subscription fails', function (): void {
    Plan::factory()->create(['price' => 0, 'stripe_price_id' => null]);
    $this->mock(SubscribeHandler::class)->shouldReceive('__invoke')->andThrow(new RuntimeException('billing offline'));

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->postJson('/api/v1/organizations', ['name' => 'Resilient'])
        ->assertCreated();
});

it('keeps slugs when an empty slug is sent', function (): void {
    [$admin, $organization] = userInOrganization('admin');
    $role = Role::factory()->create(['organization_id' => $organization->id, 'slug' => 'editor']);
    $permission = Permission::factory()->create(['organization_id' => $organization->id, 'slug' => 'posts.edit']);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->putJson("/api/v1/roles/{$role->id}", ['slug' => '', 'description' => 'Edits'])
        ->assertOk()
        ->assertJsonPath('data.slug', 'editor');

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->putJson("/api/v1/permissions/{$permission->id}", ['slug' => '', 'name' => 'Edit any post'])
        ->assertOk()
        ->assertJsonPath('data.slug', 'posts.edit');
});

it('refuses to cancel invitations without an organization context', function (): void {
    app(CancelInvitationHandler::class)(new CancelInvitation('99999999-9999-4999-9999-999999999999'));
})->throws(InvalidArgumentException::class);

it('sanitizes pagination input', function (array $query, int $perPage, int $page): void {
    $request = Request::create('/', 'GET', $query);

    expect(Pagination::perPage($request))->toBe($perPage)
        ->and(Pagination::page($request))->toBe($page);
})->with([
    [[], 15, 1],
    [['per_page' => '-5', 'page' => '-2'], 1, 1],
    [['perPage' => '20', 'page' => '3'], 20, 3],
    [['per_page' => 'abc', 'page' => 'x'], 15, 1],
    [['per_page' => '5000'], 100, 1],
]);
