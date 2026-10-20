<?php

namespace Features\Auth\Logout\Application;

use Laravel\Sanctum\PersonalAccessToken;

class LogoutUserHandler
{
    /**
     * Revoke the token used for the current request, or every token of the user.
     */
    public function __invoke(LogoutUser $command): void
    {
        if ($command->allDevices) {
            $command->user->tokens()->delete();

            return;
        }

        $token = $command->user->currentAccessToken();

        // Transient (cookie based) tokens have nothing to revoke.
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }
}
