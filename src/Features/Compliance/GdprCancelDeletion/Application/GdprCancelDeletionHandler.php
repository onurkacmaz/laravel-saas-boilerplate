<?php

namespace Features\Compliance\GdprCancelDeletion\Application;

class GdprCancelDeletionHandler
{
    public function __invoke(GdprCancelDeletion $command): void
    {
        $command->user->forceFill(['deletion_requested_at' => null])->save();
    }
}
