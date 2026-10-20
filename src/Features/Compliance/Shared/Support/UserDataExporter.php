<?php

namespace Features\Compliance\Shared\Support;

use App\Models\Role;
use App\Models\User;

/**
 * Builds the GDPR (Art. 15 / Art. 20) export of everything stored about a user.
 */
final class UserDataExporter
{
    public function export(User $user): array
    {
        $user->loadMissing(['organizations', 'ownedOrganizations.subscriptions.plan', 'tokens']);

        $roleNames = Role::query()
            ->withoutOrganization()
            ->withTrashed()
            ->whereIn('id', $user->organizations->pluck('pivot.role_id')->filter()->unique())
            ->pluck('name', 'id');

        return [
            'personal_information' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'two_factor_enabled' => $user->hasTwoFactorEnabled(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'deletion_requested_at' => $user->deletion_requested_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
                'updated_at' => $user->updated_at?->toIso8601String(),
            ],
            'organizations' => $user->organizations->map(fn ($org) => [
                'id' => $org->id,
                'name' => $org->name,
                'role' => $roleNames[$org->pivot->role_id] ?? null,
                'joined_at' => $org->pivot->created_at?->toIso8601String(),
            ])->values()->toArray(),
            'owned_organizations' => $user->ownedOrganizations->map(fn ($org) => [
                'id' => $org->id,
                'name' => $org->name,
                'created_at' => $org->created_at?->toIso8601String(),
            ])->values()->toArray(),
            'subscriptions' => $user->ownedOrganizations->flatMap(fn ($org) => $org->subscriptions->map(fn ($sub) => [
                'organization' => $org->name,
                'plan' => $sub->plan?->name,
                'status' => $sub->stripe_status,
                'started_at' => $sub->starts_at?->toIso8601String(),
                'ends_at' => $sub->ends_at?->toIso8601String(),
            ]))->values()->toArray(),
            'audit_logs' => $user->auditLogs()->latest()->limit(10000)->get()->map(fn ($log) => [
                'event' => $log->event,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at?->toIso8601String(),
            ])->toArray(),
            'api_tokens' => $user->tokens->map(fn ($token) => [
                'name' => $token->name,
                'abilities' => $token->abilities,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
            ])->values()->toArray(),
        ];
    }
}
