<?php

namespace Features\Webhooks\GetWebhookEvents\Application;

class GetWebhookEventsHandler
{
    /**
     * The events endpoints can subscribe to (`config('webhooks.events')`).
     * Modules add their own events to that list from their service provider.
     *
     * @return list<string>
     */
    public function __invoke(GetWebhookEvents $command): array
    {
        $events = array_values(array_unique(array_filter(
            (array) config('webhooks.events', []),
            fn ($event) => is_string($event) && $event !== '',
        )));

        sort($events);

        return $events;
    }
}
