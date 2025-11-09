<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('registers a user with valid credentials', function (): void {
    $ownerRole = \App\Models\Role::query()->create([
        'name' => 'Owner',
        'slug' => 'owner',
    ]);

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => ['id', 'name', 'email'],
            'status',
            'message',
        ])
        ->assertJson(['status' => 'success']);

    expect(User::where('email', 'test@example.com')->exists())->toBeTrue();
});

it('rejects registration with invalid email', function (): void {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Test User',
        'email' => 'invalid-email',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('rejects registration with duplicate email', function (): void {
    User::factory()->create(['email' => 'test@example.com']);

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('requires password confirmation during registration', function (): void {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'Password123!',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});
