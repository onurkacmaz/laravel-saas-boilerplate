<?php

namespace Features\Webhooks\GetWebhookEvents\Application;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Webhooks')]
class GetWebhookEventsController
{
    /**
     * List the events webhooks can subscribe to.
     *
     * Endpoints may also subscribe to `*` (every event) or to a prefix such
     * as `member.*`.
     */
    public function __invoke(GetWebhookEventsHandler $handler): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => __('Webhook events fetched successfully'),
            'data' => $handler(new GetWebhookEvents),
        ]);
    }
}
