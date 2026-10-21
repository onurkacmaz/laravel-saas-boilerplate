<?php

use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    config([
        'security.suspicious_activity.max_requests_per_minute' => 3,
        'security.suspicious_activity.max_violations' => 2,
        'security.suspicious_activity.block_minutes' => 10,
    ]);

    Route::middleware('security.check')->post('test-guarded', fn () => response()->json(['ok' => true]));
    Route::middleware('security.check:5')->post('test-busy', fn () => response()->json(['ok' => true]));
    Route::middleware('security.check:0')->post('test-unlimited', fn () => response()->json(['ok' => true]));
});

function hit(string $uri, int $times): array
{
    return collect(range(1, $times))->map(fn () => test()->postJson($uri)->getStatusCode())->all();
}

it('uses the configured request rate', function (): void {
    expect(hit('/test-guarded', 4))->toBe([200, 200, 200, 429]);
});

it('lets a route allow more requests per minute', function (): void {
    expect(hit('/test-busy', 6))->toBe([200, 200, 200, 200, 200, 429]);
});

it('lets a route disable the rate check', function (): void {
    expect(hit('/test-unlimited', 10))->each->toBe(200);
});

it('blocks an ip for the configured time after the configured number of violations', function (): void {
    $this->postJson('/test-unlimited', ['payload' => '<script>alert(1)</script>'])->assertStatus(400);
    $this->postJson('/test-unlimited', ['payload' => '../../etc/passwd'])->assertStatus(400);

    $this->postJson('/test-unlimited')->assertForbidden();

    $this->travel(11)->minutes();

    $this->postJson('/test-unlimited')->assertOk();
});

it('keeps the previous defaults', function (): void {
    expect((require base_path('config/security.php'))['suspicious_activity'])->toBe([
        'max_requests_per_minute' => 100,
        'max_violations' => 5,
        'violation_window_minutes' => 60,
        'block_minutes' => 1440,
    ]);
});
