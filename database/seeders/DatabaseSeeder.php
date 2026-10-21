<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Container tag of the seeders that modules add, run after the core ones:
     * $this->app->tag([ProjectsSeeder::class], DatabaseSeeder::MODULE_SEEDERS);
     */
    public const string MODULE_SEEDERS = 'database.module-seeders';

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PlanSeeder::class,
        ]);

        foreach (app()->tagged(self::MODULE_SEEDERS) as $seeder) {
            $this->call($seeder::class);
        }
    }
}
