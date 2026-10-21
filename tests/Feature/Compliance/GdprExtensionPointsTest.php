<?php

use App\Models\Organization;
use App\Models\User;
use Features\Acl\Shared\Support\SystemRoles;
use Features\Compliance\Shared\Contracts\ExportsUserData;
use Features\Compliance\Shared\Support\UserDataExporter;
use Features\Tenant\DeleteOrganization\Events\OrganizationDeleted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

function exportContributor(string $section): ExportsUserData
{
    return new class($section) implements ExportsUserData
    {
        public function __construct(private readonly string $name) {}

        public function section(): string
        {
            return $this->name;
        }

        public function export(User $user): array
        {
            return ['owner' => $user->id, 'items' => ['first', 'second']];
        }
    };
}

function tagContributor(ExportsUserData $contributor): void
{
    app()->instance('test.exporter', $contributor);
    app()->tag(['test.exporter'], UserDataExporter::CONTRIBUTORS);
}

it('adds contributed sections to the export and the download', function (): void {
    $user = User::factory()->create();
    tagContributor(exportContributor('projects'));

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/gdpr/export')
        ->assertOk()
        ->assertJsonPath('data.personal_information.id', $user->id)
        ->assertJsonPath('data.projects', ['owner' => $user->id, 'items' => ['first', 'second']]);

    $this->actingAs($user, 'sanctum')->get('/api/v1/gdpr/download')
        ->assertOk()
        ->assertJsonPath('projects.owner', $user->id);
});

it('refuses sections that are already part of the export', function (): void {
    tagContributor(exportContributor('audit_logs'));

    app(UserDataExporter::class)->export(User::factory()->create());
})->throws(LogicException::class, 'The GDPR export already has a [audit_logs] section.');

it('raises OrganizationDeleted for the organizations erasure deletes', function (): void {
    Event::fake([OrganizationDeleted::class]);
    $user = User::factory()->create();
    $deleted = Organization::factory()->create(['owner_id' => $user->id]);
    $handedOver = Organization::factory()->create(['owner_id' => $user->id]);
    $handedOver->addUser(User::factory()->create(), SystemRoles::ensure(SystemRoles::ADMIN)->id);

    $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/gdpr/delete', ['password' => 'password'])
        ->assertNoContent();

    Event::assertDispatchedTimes(OrganizationDeleted::class, 1);
    Event::assertDispatched(OrganizationDeleted::class, fn (OrganizationDeleted $event) => $event->organization->is($deleted));

    expect($deleted->fresh()->trashed())->toBeTrue()
        ->and($handedOver->fresh()->trashed())->toBeFalse();
});
