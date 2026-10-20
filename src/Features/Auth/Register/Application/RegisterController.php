<?php

namespace Features\Auth\Register\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Auth\Shared\Resources\UserResource;
use Throwable;

#[Group('Authentication')]
class RegisterController
{
    /**
     * Register a new user.
     *
     * @throws Throwable
     */
    public function __invoke(RegisterRequest $request, RegisterUserHandler $handler)
    {
        $user = $handler(
            command: new RegisterUser(
                name: (string) $request->validated('name'),
                email: (string) $request->validated('email'),
                password: (string) $request->validated('password'),
            )
        );

        return $user->toResource(UserResource::class)->additional([
            'status' => 'success',
            'message' => 'User registered successfully.',
        ]);
    }
}
