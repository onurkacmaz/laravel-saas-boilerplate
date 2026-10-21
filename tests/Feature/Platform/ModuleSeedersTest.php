<?php

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PlanSeeder;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    PlanSeeder::forgetExtensions();
});

class ModuleSeedersTestSeeder extends Seeder
{
    public static int $runs = 0;

    public function run(): void
    {
        self::$runs++;
    }
}

it('runs the seeders that modules tag after the core ones', function (): void {
    ModuleSeedersTestSeeder::$runs = 0;
    app()->tag([ModuleSeedersTestSeeder::class], DatabaseSeeder::MODULE_SEEDERS);

    $this->seed(DatabaseSeeder::class);

    expect(ModuleSeedersTestSeeder::$runs)->toBe(1)
        ->and(Plan::query()->where('slug', 'free')->exists())->toBeTrue();
});

it('merges module limits and features into the plan catalogue', function (): void {
    PlanSeeder::extend('free', ['reports' => 1], ['reports']);
    PlanSeeder::extend('free', ['users' => 4, 'exports' => 0], ['reports', 'exports']);
    PlanSeeder::extend('pro-monthly', ['reports' => 100]);

    $this->seed(PlanSeeder::class);

    $free = Plan::query()->where('slug', 'free')->sole();
    $pro = Plan::query()->where('slug', 'pro-monthly')->sole();

    expect($free->limits)->toMatchArray(['users' => 4, 'projects' => 1, 'reports' => 1, 'exports' => 0])
        ->and($free->features)->toBe(['basic-support', 'reports', 'exports'])
        ->and($pro->limits['reports'])->toBe(100)
        ->and(Plan::query()->where('slug', 'pro-yearly')->sole()->limits)->not->toHaveKey('reports');

    // Seeding again keeps the catalogue as it is.
    $this->seed(PlanSeeder::class);

    expect(Plan::query()->where('slug', 'free')->sole()->features)->toBe(['basic-support', 'reports', 'exports']);
});
