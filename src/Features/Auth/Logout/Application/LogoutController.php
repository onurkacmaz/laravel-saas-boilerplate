<?php

namespace Features\Auth\Logout\Application;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Authentication')]
class LogoutController
{
    /**
     * Log out.
     *
     * Revokes the current API token. Send `all_devices=true` to revoke every
     * token of the user.
     */
    public function __invoke(Request $request, LogoutUserHandler $handler): JsonResponse
    {
        $handler(
            command: new LogoutUser(
                user: $request->user(),
                allDevices: $request->boolean('all_devices'),
            )
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully.',
        ]);
    }
}
