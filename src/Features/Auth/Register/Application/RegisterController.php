<?php

namespace Features\Auth\Register\Application;

use Dedoc\Scramble\Attributes\Group;
use Exception;
use Features\Auth\Shared\Resources\UserResource;

#[Group('Authentication')]
class RegisterController
{
    /**
     * Register a new user.
     *
     * @throws Exception
     */
    public function __invoke(RegisterRequest $request, RegisterUserHandler $handler)
    {
        $user = $handler(
            command: new RegisterUser(
                name: $request->input('name'),
                email: $request->input('email'),
                password: $request->input('password'),
            )
        );

        return $user->toResource(UserResource::class)->additional([
            'status' => 'success',
            'message' => 'User registered successfully.',
        ]);
    }
}
