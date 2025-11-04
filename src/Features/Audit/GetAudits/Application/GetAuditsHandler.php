<?php

namespace Features\Audit\GetAudits\Application;

use Features\Audit\Infrastructure\Support\AuditLogger;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class GetAuditsHandler
{
    public function __invoke(GetAudits $command): LengthAwarePaginator
    {
        return app(AuditLogger::class)->query()
            ->when($command->event, fn (Builder $query, $event) => $query->where('event', $event))
            ->when($command->userId, fn (Builder $query, $userId) => $query->where('user_id', $userId))
            ->paginate(
                perPage: $command->perPage,
                page: $command->page,
            );
    }
}
