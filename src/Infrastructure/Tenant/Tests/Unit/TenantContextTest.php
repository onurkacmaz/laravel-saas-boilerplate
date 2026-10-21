<?php

use App\Models\Organization;
use App\Models\User;
use Infrastructure\Tenant\TenantContext;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    TenantContext::clear();
});

afterEach(function (): void {
    TenantContext::clear();
});

it('stores and retrieves tenant context information', function (): void {
    $organization = new class extends Organization
    {
        public function __construct()
        {
            parent::__construct();
            $this->setAttribute('id', 'org-123');
        }
    };

    $user = new class extends User
    {
        public function __construct()
        {
            parent::__construct();
            $this->setAttribute('id', 'user-456');
        }
    };

    TenantContext::setOrganization($organization);
    TenantContext::setUser($user);

    expect(TenantContext::hasOrganization())->toBeTrue();
    expect(TenantContext::getOrganization())->toBe($organization);
    expect(TenantContext::getOrganizationId())->toBe('org-123');
    expect(TenantContext::getUser())->toBe($user);
});

it('determines user membership information', function (): void {
    $organization = new class extends Organization
    {
        public bool $hasUserResponse = false;

        public bool $isOwnerResponse = false;

        public bool $isAdminResponse = false;

        public function hasUser(User $user): bool
        {
            return $this->hasUserResponse;
        }

        public function isOwner(User $user): bool
        {
            return $this->isOwnerResponse;
        }

        public function isAdmin(User $user): bool
        {
            return $this->isAdminResponse;
        }
    };
    $organization->setAttribute('id', 'org-123');

    $user = new class extends User
    {
        public function __construct()
        {
            parent::__construct();
            $this->setAttribute('id', 'user-456');
        }
    };

    // No context set yet should yield false
    expect(TenantContext::userBelongsToOrganization())->toBeFalse();
    expect(TenantContext::userIsOwner())->toBeFalse();
    expect(TenantContext::userIsAdmin())->toBeFalse();

    $organization->hasUserResponse = true;
    $organization->isOwnerResponse = true;
    $organization->isAdminResponse = true;

    TenantContext::setOrganization($organization);
    TenantContext::setUser($user);

    expect(TenantContext::userBelongsToOrganization())->toBeTrue();
    expect(TenantContext::userIsOwner())->toBeTrue();
    expect(TenantContext::userIsAdmin())->toBeTrue();
});

it('clears tenant context', function (): void {
    $organization = new class extends Organization
    {
        public function __construct()
        {
            parent::__construct();
            $this->setAttribute('id', 'org-123');
        }
    };

    TenantContext::setOrganization($organization);
    TenantContext::setUser(new User);

    TenantContext::clear();

    expect(TenantContext::hasOrganization())->toBeFalse();
    expect(TenantContext::getOrganization())->toBeNull();
    expect(TenantContext::getUser())->toBeNull();
});

it('runs a callback for another tenant and restores the current one', function (): void {
    $current = new Organization;
    $other = new Organization;
    TenantContext::setOrganization($current);

    $seen = TenantContext::runAs($other, fn () => TenantContext::getOrganization());

    expect($seen)->toBe($other)
        ->and(TenantContext::runWithoutTenant(fn () => TenantContext::getOrganization()))->toBeNull()
        ->and(TenantContext::getOrganization())->toBe($current);
});

it('restores the tenant when the callback throws', function (): void {
    $current = new Organization;
    TenantContext::setOrganization($current);

    try {
        TenantContext::runWithoutTenant(fn () => throw new RuntimeException('failed'));
    } catch (RuntimeException) {
        //
    }

    expect(TenantContext::getOrganization())->toBe($current);
});
