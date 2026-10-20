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

                $query->where(fn ($query) => $query->where('name', 'like', $term)->orWhere('url', 'like', $term));
            })
            ->latest()
            ->paginate(
                perPage: $command->perPage,
                page: $command->page,
            );
    }
}
