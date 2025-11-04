<?php

namespace Features\Compliance\GdprDelete\Commands;

use App\Models\User;
use Exception;
use Features\Compliance\GdprDelete\Application\GdprDelete;
use Features\Compliance\GdprDelete\Application\GdprDeleteHandler;
use Illuminate\Console\Command;

class ProcessScheduledDeletions extends Command
{
    protected $signature = 'gdpr:process-deletions';

    protected $description = 'Process scheduled user data deletions (GDPR)';

    public function handle(): int
    {
        $usersToDelete = User::query()
            ->whereNotNull('deletion_requested_at')
            ->where('deletion_requested_at', '<=', now()->subDays(30))
            ->get();

        if ($usersToDelete->isEmpty()) {
            $this->info('No users scheduled for deletion.');

            return self::SUCCESS;
        }

        $count = 0;

        foreach ($usersToDelete as $user) {
            try {
                $this->info("Deleting user: $user->email (ID: $user->id)");
                $handler = new GdprDeleteHandler;
                $handler(
                    command: new GdprDelete(
                        user: $user,
                    )
                );
                $count++;
            } catch (Exception $e) {
                $this->error("Failed to delete user $user->id: {$e->getMessage()}");
            }
        }

        $this->info("Processed $count user deletions.");

        return self::SUCCESS;
    }
}
