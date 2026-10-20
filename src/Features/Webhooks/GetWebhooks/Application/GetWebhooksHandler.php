<?php

namespace Features\Webhooks\GetWebhooks\Application;

use Illuminate\Pagination\LengthAwarePaginator;

class GetWebhooksHandler
{
    public function __invoke(GetWebhooks $command): LengthAwarePaginator
    {
        $search = $command->search !== null ? trim($command->search) : null;

        return $command->organization->webhooks()
            ->when($search, function ($query, string $search) {
                $term = '%'.addcslashes($search, '%_\\').'%';

                // Case-insensitive on every database (ILIKE on PostgreSQL).
                $query->where(fn ($query) => $query->whereLike('name', $term)->orWhereLike('url', $term));
            })
            ->latest()
            ->paginate(
                perPage: $command->perPage,
                page: $command->page,
            );
    }
}
