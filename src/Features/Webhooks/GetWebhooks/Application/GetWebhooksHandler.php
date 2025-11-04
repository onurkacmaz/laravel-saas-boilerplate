<?php

namespace Features\Webhooks\GetWebhooks\Application;

use Illuminate\Pagination\LengthAwarePaginator;

class GetWebhooksHandler
{
    public function __invoke(GetWebhooks $command): LengthAwarePaginator
    {
        return $command->organization->webhooks()->paginate(
            perPage: $command->perPage,
            page: $command->page,
        );
    }
}
