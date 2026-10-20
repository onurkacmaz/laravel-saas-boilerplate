<?php

use App\Models\Organization;
use App\Models\User;
use Features\Audit\Infrastructure\Eloquent\Models\AuditLog;
use Features\Audit\Infrastructure\Middleware\AuditMiddleware;
use Features\Audit\Infrastructure\Support\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Infrastructure\Tenant\TenantContext;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create(['name' => 'Ada']);
    $this->organization = Organization::factory()->create(['name' => 'Acme']);
    Auth::setUser($this->user);
    TenantContext::setOrganization($this->organization);
    $this->logger = app(AuditLogger::class);
});

afterEach(fn () => TenantContext::clear());

it('records model lifecycle events with redacted secrets', function (): void {
    $created = $this->logger->created($this->organization, ['source' => 'test']);

    $this->organization->name = 'Acme 2';
    $updated = $this->logger->updated($this->organization);
    $deleted = $this->logger->deleted($this->organization);

    $sensitive = $this->logger->log('settings.changed', newValues: [
        'Password' => 'hunter2',
        'nested' => ['api_key' => 'abc', 'safe' => 'ok'],
        'list' => [['token' => 't']],
    ]);

    expect($created->organization_id)->toBe($this->organization->id)
        ->and($created->user_id)->toBe($this->user->id)
        ->and($created->metadata)->toBe(['source' => 'test'])
        ->and($created->description)->toBe('Ada created Organization')
        ->and($updated->changes['name'])->toBe(['old' => 'Acme', 'new' => 'Acme 2'])
        ->and($deleted->event)->toBe('deleted')
        ->and($deleted->new_values)->toBe([])
        ->and($sensitive->new_values)->toBe([
            'Password' => '[REDACTED]',
            'nested' => ['api_key' => '[REDACTED]', 'safe' => 'ok'],
            'list' => [['token' => '[REDACTED]']],
        ]);
});

it('provides scoped query helpers', function (): void {
    $this->logger->auth('login', ['ip' => '1.2.3.4']);
    $this->logger->action('export', $this->organization);
    AuditLog::factory()->create();

    expect($this->logger->query()->count())->toBe(2)
        ->and($this->logger->forEvent('auth.login')->count())->toBe(1)
        ->and($this->logger->forModel($this->organization)->count())->toBe(1)
        ->and($this->logger->forUser($this->user->id)->count())->toBe(2);

    TenantContext::clear();

    expect($this->logger->query()->count())->toBe(0);
});

it('describes system events without a user', function (): void {
    Auth::forgetUser();

    $log = $this->logger->action('nightly.cleanup');

    expect($log->user_id)->toBeNull()
        ->and($log->description)->toBe('System nightly.cleanup')
        ->and($log->changes)->toBe([]);
});

it('derives an action name for routes without a name', function (): void {
    $middleware = app(AuditMiddleware::class);
    $request = Request::create('/api/v1/things/'.fake()->uuid(), 'DELETE');
    $request->setUserResolver(fn () => $this->user);

    $middleware->handle($request, fn () => response()->noContent());

    expect(AuditLog::query()->latest('created_at')->value('event'))->toBe('delete.things');
});

it('skips reads, failures, guests and disabled auditing', function (): void {
    $middleware = app(AuditMiddleware::class);
    $make = function (string $method) {
        $request = Request::create('/api/v1/things', $method);
        $request->setUserResolver(fn () => $this->user);

        return $request;
    };

    $middleware->handle($make('GET'), fn () => response('ok'));
    $middleware->handle($make('POST'), fn () => response('nope', 422));
    $middleware->handle(Request::create('/api/v1/things', 'POST'), fn () => response('ok'));

    config(['audit.enabled' => false]);
    $middleware->handle($make('POST'), fn () => response('ok'));

    expect(AuditLog::query()->count())->toBe(0);
});
