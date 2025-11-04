<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('logs in with valid credentials', function (): void {
    User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('Password123!'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'Password123!',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'name', 'email', 'token'],
            'status',
            'message',
        ]);
});

it('rejects login with invalid password', function (): void {
    User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('Password123!'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'WrongPassword',
    ]);

    $response->assertUnauthorized()
        ->assertJson([
            'message' => 'Invalid email or password.',
            'status' => 'error',
        ]);
});

it('rejects login with nonexistent email', function (): void {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'nonexistent@example.com',
        'password' => 'Password123!',
    ]);

    $response->assertUnauthorized()
        ->assertJson([
            'message' => 'Invalid email or password.',
            'status' => 'error',
        ]);
});
