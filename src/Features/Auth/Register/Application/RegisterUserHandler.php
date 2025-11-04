<?php

namespace Features\Auth\Register\Application;

use App\Models\User;
use Exception;
use Features\Auth\Register\Events\UserRegisterFailed;
use Features\Auth\Register\Events\UserRegisterSucceeded;
use Illuminate\Contracts\Auth\Authenticatable;

class RegisterUserHandler
{
    /**
     * @throws Exception
     */
    public function __invoke(RegisterUser $command): Authenticatable
    {
        $attributes = [
            'name' => $command->name,
            'email' => $command->email,
            'password' => bcrypt($command->password),
        ];

        try {
            $user = User::query()->create(
                attributes: $attributes
            );

            $user->assignDefaultRole();

            UserRegisterSucceeded::dispatch($user);

            return $user->refresh();
        } catch (Exception $e) {
            UserRegisterFailed::dispatch('web', null, $attributes);
            throw $e;
        }
    }
}
