<?php

namespace Features\Compliance\GdprRequestDeletion\Application;

class GdprRequestDeletionHandler
{
    public function __invoke(GdprRequestDeletion $command): void
    {
        $command->user->update([
            'deletion_requested_at' => null,
        ]);
    }
}
