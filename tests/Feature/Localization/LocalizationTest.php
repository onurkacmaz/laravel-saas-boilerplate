<?php

use App\Models\User;
use Features\Acl\Shared\Support\SystemRoles;
use Features\Auth\TwoFactorAuth\Notifications\TwoFactorEnabledNotification;
use Features\Tenant\SendInvitation\Notifications\OrganizationInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('answers in English by default', function () {
    $this->postJson('/api/v1/auth/login', ['email' => 'nobody@example.com', 'password' => 'wrong-password'])
        ->assertUnauthorized()
        ->assertHeader('Content-Language', 'en')
        ->assertJsonPath('message', 'Invalid email or password.');
});

it('answers in the language of the Accept-Language header', function () {
    $this->postJson('/api/v1/auth/login', ['email' => 'nobody@example.com', 'password' => 'wrong-password'], [
        'Accept-Language' => 'tr-TR,tr;q=0.9,en;q=0.8',
    ])
        ->assertUnauthorized()
        ->assertHeader('Content-Language', 'tr')
        ->assertJsonPath('message', 'E-posta adresi ya da şifre hatalı.');
});

it('falls back to English for languages it does not have', function () {
    $this->postJson('/api/v1/auth/login', ['email' => 'nobody@example.com', 'password' => 'wrong-password'], [
        'Accept-Language' => 'de-DE,de;q=0.9',
    ])->assertHeader('Content-Language', 'en')->assertJsonPath('message', 'Invalid email or password.');
});

it('translates validation errors and field names', function () {
    $this->postJson('/api/v1/auth/register', ['name' => 'Ayşe'], ['Accept-Language' => 'tr'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'e-posta alanı zorunludur.')
        ->assertJsonPath('errors.password.0', 'şifre alanı zorunludur.');
});

it('translates messages of errors raised deep in the application', function () {
    [$user, $organization] = userInOrganization('owner');

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/invitations', [
            'email' => $user->email,
            'role_id' => $organization->users()->first()->pivot->role_id,
        ], ['X-Organization-Id' => $organization->id, 'Accept-Language' => 'tr'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Kendinizi davet edemezsiniz.');
});

it('translates authentication errors from the framework', function () {
    $this->getJson('/api/v1/user', ['Accept-Language' => 'tr'])
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Oturum açmanız gerekiyor.');
});

it('remembers the language a user signed up in, for their e-mails', function () {
    Notification::fake();

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Ayşe Demir',
        'email' => 'ayse@example.com',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
    ], ['Accept-Language' => 'tr'])->assertCreated();

    $user = User::query()->where('email', 'ayse@example.com')->firstOrFail();
    expect($user->locale)->toBe('tr')->and($user->preferredLocale())->toBe('tr');
});

it('lets users change the language of their e-mails', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/v1/auth/profile', ['locale' => 'tr'])
        ->assertOk()
        ->assertJsonPath('data.locale', 'tr');

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/v1/auth/profile', ['locale' => 'xx'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('locale');

    expect($user->fresh()->locale)->toBe('tr');
});

it('sends e-mails to users in their language', function () {
    Notification::fake();
    $user = User::factory()->create(['locale' => 'tr']);

    $user->notify(new TwoFactorEnabledNotification($user));

    Notification::assertSentTo($user, TwoFactorEnabledNotification::class, function ($notification, $channels, $notifiable, $locale) use ($user) {
        app()->setLocale($locale);

        return $locale === 'tr'
            && $notification->toMail($user)->subject === 'İki adımlı doğrulama açıldı';
    });
});

it('writes invitations in the language of the person who invites', function () {
    Notification::fake();
    [$user, $organization] = userInOrganization('owner');

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/invitations', [
            'email' => 'new@example.com',
            'role_id' => SystemRoles::ensure(SystemRoles::MEMBER)->id,
        ], ['X-Organization-Id' => $organization->id, 'Accept-Language' => 'tr'])
        ->assertCreated();

    Notification::assertSentOnDemand(OrganizationInvitation::class, fn ($notification) => $notification->locale === 'tr');
});

it('has a Turkish translation for every message', function () {
    // The core's lang/tr.json and the files modules load with loadJsonTranslationsFrom().
    $translations = collect([lang_path(), ...app('translation.loader')->jsonPaths()])
        ->map(fn (string $directory) => $directory.'/tr.json')
        ->filter(fn (string $file) => is_file($file))
        ->flatMap(fn (string $file) => json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR))
        ->all();
    $missing = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('src')));
    foreach ([...$files, ...new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path()))] as $file) {
        if ($file->getExtension() !== 'php' || str_contains($file->getPathname(), '/Tests/')) {
            continue;
        }

        preg_match_all("/\\b(?:__|trans_choice)\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/", file_get_contents($file->getPathname()), $matches);

        foreach ($matches[1] as $key) {
            $key = stripslashes($key);
            if (! array_key_exists($key, $translations)) {
                $missing[] = $key;
            }
        }
    }

    expect(array_values(array_unique($missing)))->toBe([]);
});

it('has every validation message in Turkish', function () {
    $english = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');
    $turkish = require lang_path('tr/validation.php');

    $keys = fn (array $lines) => collect($lines)->except(['custom', 'attributes'])
        ->flatMap(fn ($value, $key) => is_array($value) ? collect($value)->keys()->map(fn ($sub) => "$key.$sub") : [$key])
        ->sort()->values()->all();

    expect($keys($turkish))->toBe($keys($english));
});
