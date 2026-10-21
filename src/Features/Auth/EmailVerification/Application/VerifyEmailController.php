<?php

namespace Features\Auth\EmailVerification\Application;

use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;

#[Group('Authentication')]
class VerifyEmailController
{
    /**
     * Verify an e-mail address.
     *
     * Called with the signed URL sent by e-mail. The signature (checked by the
     * `signed` middleware) and the e-mail hash protect this endpoint, so it does
     * not require authentication.
     */
    public function __invoke(string $id, string $hash): JsonResponse
    {
        $user = User::query()->find($id);

        if (! $user || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return response()->json([
                'status' => 'error',
                'message' => __('Invalid verification link.'),
            ], 403);
        }

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json([
            'status' => 'success',
            'message' => __('E-mail address verified.'),
        ]);
    }
}
