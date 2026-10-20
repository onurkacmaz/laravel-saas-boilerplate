<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Infrastructure\Security\Middleware\SecurityHeaders;

uses(RefreshDatabase::class);

it('documents only the API and the tenant header of tenant routes', function (): void {
    Gate::define('viewApiDocs', fn (?User $user = null) => true);

    $spec = $this->getJson('/docs/api.json')->assertOk()->json();

    $paths = array_keys($spec['paths']);
    $headers = fn (string $path, string $method) => collect($spec['paths'][$path][$method]['parameters'] ?? [])
        ->where('in', 'header')->pluck('name')->all();

    expect($paths)->not->toContain('/stripe/webhook')
        ->and($paths)->toContain('/v1/organizations')
        ->and($headers('/v1/invitations', 'get'))->toBe(['X-Organization-Id'])
        ->and($headers('/v1/organizations', 'get'))->toBe([])
        ->and($spec['security'] ?? [])->not->toBe([]);
});

it('points authentication e-mails to the frontend when configured', function (): void {
    $user = User::factory()->unverified()->create(['email' => 'ada@example.com']);

    config(['app.frontend_url' => 'https://app.example.test']);

    $reset = (new ResetPassword('reset-token'))->toMail($user)->actionUrl;
    $verify = (new VerifyEmail)->toMail($user)->actionUrl;

    expect($reset)->toBe('https://app.example.test/reset-password?token=reset-token&email=ada%40example.com')
        ->and($verify)->toStartWith('https://app.example.test/verify-email?verify_url=');

    config(['app.frontend_url' => null]);

    expect((new VerifyEmail)->toMail($user)->actionUrl)->toStartWith(config('app.url').'/api/v1/auth/email/verify/');
});

it('answers API errors with JSON even without an Accept header', function (): void {
    $this->get('/api/v1/does-not-exist')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/json');
});

it('sends strict security headers on API responses', function (): void {
    $response = $this->getJson('/api/v1/plans')->assertOk();

    $response->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Security-Policy');

    expect($response->headers->has('Strict-Transport-Security'))->toBeFalse();
});

it('adds HSTS for secure production requests', function (): void {
    app()->detectEnvironment(fn () => 'production');

    $response = (new SecurityHeaders)->handle(
        Request::create('https://api.example.test/api/v1/plans'),
        fn () => response('ok')
    );

    expect($response->headers->get('Strict-Transport-Security'))->toContain('max-age=31536000');
});
