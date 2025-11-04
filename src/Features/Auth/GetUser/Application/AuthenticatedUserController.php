<?php

namespace Features\Auth\GetUser\Application;

use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Features\Auth\Shared\Resources\UserResource;
use Illuminate\Http\Resources\Json\JsonResource;

#[Group('Authentication')]
class AuthenticatedUserController
{
    /**
     * Get the authenticated user.
     */
    public function __invoke(): JsonResource
    {
        /**
         * @var User $user
         */
        $user = auth()->user();

        return $user->toResource(UserResource::class)->additional([
            'status' => 'success',
            'message' => 'User retrieved successfully.',
        ]);
    }
}
