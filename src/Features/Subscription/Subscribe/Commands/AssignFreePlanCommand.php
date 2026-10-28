<?php

namespace Features\Subscription\Subscribe\Commands;

use App\Models\Organization;
use Features\Subscription\Subscribe\Application\AssignFreePlan;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class AssignFreePlanCommand extends Command
{
    protected $signature = 'subscriptions:assign-free-plan
        {--dry-run : List the organizations without subscribing them}';

    protected $description = 'Put organizations without an active plan on the free plan';

    public function handle(AssignFreePlan $assign): int
    {
        if (! AssignFreePlan::freePlanId()) {
            $this->error('No active free plan exists. Seed the plan catalogue first: php artisan db:seed');

            return self::FAILURE;
        }

        // Free plans and Stripe subscriptions are filtered in the query; a
        // subscription of another provider (Paddle) lives in its own table.
        $organizations = Organization::query()
            ->whereDoesntHave('subscriptions', fn (Builder $query) => $query->whereIn('stripe_status', ['active', 'trialing']));

        if ($this->option('dry-run')) {
            $count = 0;

            $organizations->orderBy('name')->each(function (Organization $organization) use (&$count): void {
                if (! $organization->hasActiveSubscription()) {
                    $this->line("{$organization->id}  {$organization->name}");
                    $count++;
                }
            });

            $this->info("$count organization(s) have no active plan.");

            return self::SUCCESS;
        }

        $assigned = 0;
        $failed = 0;

        // Keyset pagination: subscribing removes organizations from the query.
        $organizations->lazyById()->each(function (Organization $organization) use ($assign, &$assigned, &$failed): void {
            if ($organization->hasActiveSubscription()) {
                return;
            }

            if ($assign($organization)) {
                $assigned++;
            } else {
                $failed++;
                $this->warn("Could not subscribe {$organization->id} ({$organization->name}). See the log.");
            }
        });

        $this->info("Put $assigned organization(s) on the free plan.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
