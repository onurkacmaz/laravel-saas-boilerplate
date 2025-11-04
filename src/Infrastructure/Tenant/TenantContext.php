<?php

namespace Infrastructure\Tenant;

use App\Models\Organization;
use App\Models\User;

class TenantContext
{
    private static ?Organization $currentOrganization = null;

    private static ?User $currentUser = null;

    public static function setOrganization(?Organization $organization): void
    {
        self::$currentOrganization = $organization;
    }

    public static function getOrganization(): ?Organization
    {
        return self::$currentOrganization;
    }

    public static function getOrganizationId(): ?string
    {
        return self::$currentOrganization?->id;
    }

    public static function setUser(?User $user): void
    {
        self::$currentUser = $user;
    }

    public static function getUser(): ?User
    {
        return self::$currentUser;
    }

    public static function clear(): void
    {
        self::$currentOrganization = null;
        self::$currentUser = null;
    }

    public static function hasOrganization(): bool
    {
        return self::$currentOrganization !== null;
    }

    public static function userBelongsToOrganization(): bool
    {
        if (! self::$currentUser || ! self::$currentOrganization) {
            return false;
        }

        return self::$currentOrganization->hasUser(self::$currentUser);
    }

    public static function userIsOwner(): bool
    {
        if (! self::$currentUser || ! self::$currentOrganization) {
            return false;
        }

        return self::$currentOrganization->isOwner(self::$currentUser);
    }

    public static function userIsAdmin(): bool
    {
        if (! self::$currentUser || ! self::$currentOrganization) {
            return false;
        }

        return self::$currentOrganization->isAdmin(self::$currentUser);
    }
}
