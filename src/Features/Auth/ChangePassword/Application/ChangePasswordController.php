<?php

namespace Features\Auth\ChangePassword\Application;

use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Laravel\Sanctum\PersonalAccessToken;

#[Group('Authentication')]
class ChangePasswordController
{
    /**
     * Change the password of the authenticated user.
     *
     * Every other API token of the user is revoked; the token used for this
     * request stays valid.
     */
    public function __invoke(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->forceFill(['password' => $request->validated('password')])->save();

        $current = $user->currentAccessToken();

        $user->tokens()
            ->when($current instanceof PersonalAccessToken, fn ($query) => $query->whereKeyNot($current->getKey()))
            ->delete();

        return response()->json([
            'status' => 'success',
            'message' => __('Password changed successfully.'),
        ]);
    }
}
