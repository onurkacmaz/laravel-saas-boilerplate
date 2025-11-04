<?php

namespace Features\Compliance\GdprDownload\Application;

class GdprDownloadHandler
{
    public function __invoke(GdprDownload $command): array
    {
        return [
            'personal_information' => [
                'id' => $command->user->id,
                'name' => $command->user->name,
                'email' => $command->user->email,
                'email_verified_at' => $command->user->email_verified_at?->toIso8601String(),
                'created_at' => $command->user->created_at->toIso8601String(),
                'updated_at' => $command->user->updated_at->toIso8601String(),
            ],
            'organizations' => $command->user->organizations->map(fn ($org) => [
                'id' => $org->id,
                'name' => $org->name,
                'role' => $org->pivot->role,
                'joined_at' => $org->pivot->created_at?->toIso8601String(),
            ])->toArray(),
            'owned_organizations' => $command->user->ownedOrganizations->map(fn ($org) => [
                'id' => $org->id,
                'name' => $org->name,
                'created_at' => $org->created_at->toIso8601String(),
            ])->toArray(),
            'subscriptions' => $command->user->ownedOrganizations->flatMap(fn ($org) => $org->subscriptions->map(fn ($sub) => [
                'organization' => $org->name,
                'plan' => $sub->plan->name ?? null,
                'status' => $sub->stripe_status,
                'started_at' => $sub->starts_at?->toIso8601String(),
                'ends_at' => $sub->ends_at?->toIso8601String(),
            ])
            )->toArray(),
            'audit_logs' => $command->user->auditLogs()->get()->map(fn ($log) => [
                'event' => $log->event,
                'description' => $log->description,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at->toIso8601String(),
            ])->toArray(),
            'api_tokens' => $command->user->tokens->map(fn ($token) => [
                'name' => $token->name,
                'abilities' => $token->abilities,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'created_at' => $token->created_at->toIso8601String(),
            ])->toArray(),
        ];
    }
}
