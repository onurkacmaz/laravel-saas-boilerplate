<?php

use Illuminate\Support\Facades\File;

afterEach(function (): void {
    File::deleteDirectory(base_path('src/Features/ZzScaffold'));
});

it('scaffolds a complete feature slice', function (): void {
    $this->artisan('make:feature', ['name' => 'zz-scaffold:send report'])
        ->expectsOutputToContain('Feature created successfully: ZzScaffold:SendReport')
        ->assertSuccessful();

    $base = base_path('src/Features/ZzScaffold/SendReport');

    foreach ([
        'Routes/api.php',
        'Providers/SendReportServiceProvider.php',
        'Application/SendReport.php',
        'Application/SendReportHandler.php',
        'Application/SendReportController.php',
        'Application/SendReportRequest.php',
    ] as $file) {
        expect(File::exists("{$base}/{$file}"))->toBeTrue();
    }

    expect(File::get("{$base}/Providers/SendReportServiceProvider.php"))
        ->toContain('namespace Features\ZzScaffold\SendReport\Providers;')
        ->toContain('loadApiRoutesFrom');

    $this->artisan('make:feature', ['name' => 'ZzScaffold:SendReport'])
        ->expectsOutputToContain('already exists')
        ->assertFailed();

    $this->artisan('make:feature', ['name' => 'ZzScaffold:SendReport', '--force' => true])->assertSuccessful();
});

it('validates the feature name', function (string $name): void {
    $this->artisan('make:feature', ['name' => $name])->assertFailed();
})->with(['NoModule', 'Module:', 'Mod:Bad$Name', '1Module:Feature']);
