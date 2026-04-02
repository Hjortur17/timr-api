<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create([
        'company_id' => $this->company->id,
        'password' => bcrypt('password123'),
    ]);
    $this->user->companies()->attach($this->company, ['role' => 'owner']);
});

it('resets password with a valid token', function () {
    $token = Password::createToken($this->user);

    $this->postJson('/api/auth/reset-password', [
        'token' => $token,
        'email' => $this->user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])->assertOk();

    $this->user->refresh();
    expect(Hash::check('newpassword123', $this->user->password))->toBeTrue();
});

it('deletes all tokens after password reset', function () {
    $this->user->createToken('auth-token');
    expect($this->user->tokens()->count())->toBe(1);

    $token = Password::createToken($this->user);

    $this->postJson('/api/auth/reset-password', [
        'token' => $token,
        'email' => $this->user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])->assertOk();

    expect($this->user->tokens()->count())->toBe(0);
});

it('fails with an invalid token', function () {
    $this->postJson('/api/auth/reset-password', [
        'token' => 'invalid-token',
        'email' => $this->user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])->assertUnprocessable();
});

it('fails with an expired token', function () {
    $token = Password::createToken($this->user);

    $this->travel(61)->minutes();

    $this->postJson('/api/auth/reset-password', [
        'token' => $token,
        'email' => $this->user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])->assertUnprocessable();
});

it('fails when passwords do not match', function () {
    $token = Password::createToken($this->user);

    $this->postJson('/api/auth/reset-password', [
        'token' => $token,
        'email' => $this->user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'differentpassword',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('validates all fields are required', function () {
    $this->postJson('/api/auth/reset-password', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['token', 'email', 'password']);
});
