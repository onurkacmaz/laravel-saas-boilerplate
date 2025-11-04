<?php

namespace Features\Audit\GetEvents\Application;

use Features\Audit\Infrastructure\Eloquent\Models\AuditLog;
use Illuminate\Support\Collection;

class GetEventsHandler
{
    public function __invoke(GetEvents $command): Collection
    {
        return AuditLog::query()
            ->select('event')
            ->distinct()
            ->pluck('event');
    }
}
