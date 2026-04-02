<?php

use App\Models\Company;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create([
        'company_id' => $this->company->id,
        'password' => bcrypt('password123'),
    ]);
    $this->user->companies()->attach($this->company, ['role' => 'owner']);
});

it('logs in with valid credentials', function () {
    $response = $this->postJson('/api/auth/login', [
        'email' => $this->user->email,
        'password' => 'password123',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['data', 'token', 'message'])
        ->assertJsonPath('data.email', $this->user->email);
});

it('fails login with invalid credentials', function () {
    $this->postJson('/api/auth/login', [
        'email' => $this->user->email,
        'password' => 'wrong-password',
    ])->assertUnprocessable();
});

it('fails login with missing fields', function () {
    $this->postJson('/api/auth/login', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});

it('creates token without expiry when remember is true', function () {
    $response = $this->postJson('/api/auth/login', [
        'email' => $this->user->email,
        'password' => 'password123',
        'remember' => true,
    ]);

    $response->assertOk();

    $token = $this->user->tokens()->latest()->first();
    expect($token->expires_at)->toBeNull();
});

it('creates token with 24h expiry when remember is false', function () {
    $response = $this->postJson('/api/auth/login', [
        'email' => $this->user->email,
        'password' => 'password123',
        'remember' => false,
    ]);

    $response->assertOk();

    $token = $this->user->tokens()->latest()->first();
    expect($token->expires_at)->not->toBeNull();
    expect($token->expires_at->diffInHours(now(), true))->toBeBetween(23, 25);
});

it('creates token with 24h expiry when remember is omitted', function () {
    $response = $this->postJson('/api/auth/login', [
        'email' => $this->user->email,
        'password' => 'password123',
    ]);

    $response->assertOk();

    $token = $this->user->tokens()->latest()->first();
    expect($token->expires_at)->not->toBeNull();
    expect($token->expires_at->diffInHours(now(), true))->toBeBetween(23, 25);
});

it('logs out an authenticated user', function () {
    $this->actingAs($this->user);

    $this->postJson('/api/auth/logout')
        ->assertOk()
        ->assertJsonPath('message', 'Logged out successfully.');
});
