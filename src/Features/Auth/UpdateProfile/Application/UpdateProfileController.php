<?php

namespace Features\Auth\UpdateProfile\Application;

use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Features\Auth\Shared\Resources\UserResource;
use Features\Auth\UpdateProfile\Notifications\EmailChangedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;

#[Group('Authentication')]
class UpdateProfileController
{
    /**
     * Update the name or e-mail address of the authenticated user.
     *
     * Changing the e-mail address needs `current_password`. The new address
     * must be verified again: a verification link is sent to it, and the old
     * address is told about the change.
     */
    public function __invoke(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $previousEmail = $user->email;
        $changesEmail = $request->changesEmail();

        if ($request->has('name')) {
            $user->name = $request->validated('name');
        }

        if ($changesEmail) {
            $user->forceFill([
                'email' => $request->validated('email'),
                'email_verified_at' => null,
            ]);
        }

        $user->save();

        if ($changesEmail) {
            $user->sendEmailVerificationNotification();
            Notification::route('mail', $previousEmail)->notify(new EmailChangedNotification($user->name, $user->email));
        }

        return response()->json([
            'data' => new UserResource($user),
            'status' => 'success',
            'message' => $changesEmail
                ? 'Profile updated. Check your new e-mail address for a verification link.'
                : 'Profile updated.',
        ]);
    }
}
