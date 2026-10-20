<?php

namespace Features\Compliance\GdprDelete\Commands;

use App\Models\User;
use Features\Compliance\GdprDelete\Application\GdprDelete;
use Features\Compliance\GdprDelete\Application\GdprDeleteHandler;
use Illuminate\Console\Command;
use Throwable;

class ProcessScheduledDeletions extends Command
{
    protected $signature = 'gdpr:process-deletions';

    protected $description = 'Process scheduled user data deletions (GDPR)';

    public function handle(): int
    {
        $usersToDelete = User::query()
            ->whereNotNull('deletion_requested_at')
            ->where('deletion_requested_at', '<=', now()->subDays((int) config('gdpr.deletion_grace_days', 30)))
            ->get();

        if ($usersToDelete->isEmpty()) {
            $this->info('No users scheduled for deletion.');

            return self::SUCCESS;
        }

        $count = 0;

        foreach ($usersToDelete as $user) {
            try {
                // Do not print personal data (e-mail) to logs/console output.
                $this->info("Deleting user ID: $user->id");
                $handler = app(GdprDeleteHandler::class);
                $handler(
                    command: new GdprDelete(
                        user: $user,
                    )
                );
                $count++;
            } catch (Throwable $e) {
                $this->error("Failed to delete user $user->id: {$e->getMessage()}");
            }
        }

        $this->info("Processed $count user deletions.");

        return $count === $usersToDelete->count() ? self::SUCCESS : self::FAILURE;
    }
}
