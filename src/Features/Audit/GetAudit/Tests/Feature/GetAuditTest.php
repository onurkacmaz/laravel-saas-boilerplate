<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Features\Audit\Infrastructure\Eloquent\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('can get audit', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->ownedBy($user)->create([
        'name' => 'Test Organization',
        'slug' => 'test-organization',
    ]);

    // Add user to the organization to pass tenant context validation
    $role = Role::factory()->create([
        'organization_id' => $organization->id,
        'slug' => 'owner',
        'name' => 'Owner',
    ]);
    $user->organizations()->attach($organization->id, ['role_id' => $role->id]);

    $auditLog = AuditLog::factory()
        ->forUser($user)
        ->forOrganization($organization)
        ->create();

    $this->actingAs($user, 'sanctum');

    $response = $this->getJson(route('api.v1.audit-logs.show', $auditLog->id), [
        'X-Organization-Id' => $organization->id,
    ]);

    $response->assertOk();

    $response->assertJson([
        'data' => [
            'id' => $auditLog->id,
            'event' => $auditLog->event,
            'description' => $auditLog->description,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'auditable' => null,
            'changes' => [],
            'metadata' => [],
            'ip_address' => $auditLog->ip_address,
            'user_agent' => $auditLog->user_agent,
            'created_at' => $auditLog->created_at->toIso8601String(),
        ],
        'status' => 'success',
        'message' => 'Audit log retrieved successfully.',
    ]);
});
