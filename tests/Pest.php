<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Features\Acl\Shared\Support\SystemRoles;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Attach a new user to an organization (created when none is given) with the
 * role of the given slug: the shared system role for "owner", "admin" and
 * "member", as in production, or an organization scoped role otherwise. An
 * "owner" of a new organization is also its owner (`owner_id`).
 *
 * @return array{0: User, 1: Organization, 2: Role}
 */
function userInOrganization(string $roleSlug = 'member', ?Organization $organization = null): array
{
    $user = User::factory()->create();
    $organization ??= Organization::factory()->create(
        $roleSlug === SystemRoles::OWNER ? ['owner_id' => $user->id] : []
    );

    $role = array_key_exists($roleSlug, SystemRoles::definitions())
        ? SystemRoles::ensure($roleSlug)
        : Role::query()->withoutOrganization()
            ->where('organization_id', $organization->id)
            ->where('slug', $roleSlug)
            ->first()
            ?? Role::factory()->create([
                'organization_id' => $organization->id,
                'slug' => $roleSlug,
                'name' => ucfirst($roleSlug),
            ]);

    $organization->users()->attach($user->id, ['role_id' => $role->id]);

    return [$user, $organization, $role];
}

/**
 * Switch the authenticated API identity inside a single test: forget the
 * resolved guards (Sanctum caches the user between requests) and send the
 * given bearer token, or none.
 */
function actingWithToken(?string $token)
{
    $test = test();
    app('auth')->forgetGuards();
    $test->flushHeaders();

    return $token === null ? $test : $test->withToken($token);
}
