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
     * Update the name, e-mail address or language of the authenticated user.
     *
     * The language (`locale`, one of `config('app.supported_locales')`) is the
     * one of the e-mails sent to the user.
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

        if ($request->has('locale')) {
            $user->locale = $request->validated('locale');
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
            Notification::route('mail', $previousEmail)->notify(
                (new EmailChangedNotification($user->name, $user->email))->locale($user->preferredLocale() ?? app()->getLocale())
            );
        }

        return response()->json([
            'data' => new UserResource($user),
            'status' => 'success',
            'message' => $changesEmail
                ? __('Profile updated. Check your new e-mail address for a verification link.')
                : __('Profile updated.'),
        ]);
    }
}
