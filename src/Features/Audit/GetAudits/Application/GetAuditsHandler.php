<?php

namespace Features\Audit\GetAudits\Application;

use Features\Audit\Infrastructure\Support\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class GetAuditsHandler
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {}

    public function __invoke(GetAudits $command): LengthAwarePaginator
    {
        return $this->auditLogger->query()
            ->when($command->event, fn (Builder $query, $event) => $query->where('event', $event))
            ->when($command->userId, fn (Builder $query, $userId) => $query->where('user_id', $userId))
            ->when($command->from, fn (Builder $query, $from) => $query->where('created_at', '>=', Carbon::parse($from)))
            ->when($command->to, fn (Builder $query, $to) => $query->where('created_at', '<=', Carbon::parse($to)))
            ->paginate(
                perPage: $command->perPage,
                page: $command->page,
            );
    }
}
