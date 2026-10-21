<?php

namespace Features\Auth\Register\Application;

use App\Models\User;
use Features\Auth\Register\Events\UserRegisterFailed;
use Features\Auth\Register\Events\UserRegisterSucceeded;
use Illuminate\Support\Facades\DB;
use Throwable;

class RegisterUserHandler
{
    /**
     * Create the user, assign the default role and fire the registration event.
     *
     * Everything (including listeners such as the personal organization
     * creation) runs in one transaction, so a failure never leaves a half
     * registered account behind.
     *
     * @throws Throwable
     */
    public function __invoke(RegisterUser $command): User
    {
        $email = User::normalizeEmail($command->email);

        try {
            return DB::transaction(function () use ($command, $email) {
                /** @var User $user */
                $user = User::query()->create([
                    'name' => $command->name,
                    'email' => $email,
                    'password' => $command->password,
                    // E-mails go out in the language the user signed up in.
                    'locale' => app()->getLocale(),
                ]);

                $user->assignDefaultRole();

                UserRegisterSucceeded::dispatch($user);

                return $user->refresh();
            });
        } catch (Throwable $e) {
            // Never pass the password (hashed or not) along with the event.
            UserRegisterFailed::dispatch('sanctum', null, [
                'name' => $command->name,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
