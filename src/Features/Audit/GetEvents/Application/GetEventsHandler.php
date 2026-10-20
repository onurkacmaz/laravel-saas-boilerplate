<?php

namespace Features\Audit\GetEvents\Application;

use Features\Audit\Infrastructure\Eloquent\Models\AuditLog;
use Illuminate\Support\Collection;
use Infrastructure\Tenant\TenantContext;

class GetEventsHandler
{
    public function __invoke(GetEvents $command): Collection
    {
        $search = $command->search !== null ? trim($command->search) : null;

        return AuditLog::query()
            ->where('organization_id', TenantContext::getOrganizationId())
            ->when($search, fn ($query, string $search) => $query->whereLike('event', '%'.addcslashes($search, '%_\\').'%'))
            ->distinct()
            ->orderBy('event')
            ->limit(500)
            ->pluck('event');
    }
}
