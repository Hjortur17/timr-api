<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;

it('registers a new user without a company', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'John Manager',
        'email' => 'john@acme.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'John Manager')
        ->assertJsonPath('data.email', 'john@acme.com')
        ->assertJsonPath('data.company_id', null)
        ->assertJsonStructure(['data', 'token', 'message']);

    expect(Company::count())->toBe(0);
    expect(User::withoutGlobalScope('company')->count())->toBe(1);

    $user = User::withoutGlobalScope('company')->first();
    expect($user->company_id)->toBeNull();
    expect($user->companies)->toHaveCount(0);
});

it('fails registration with missing fields', function () {
    $this->postJson('/api/auth/register', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

it('registers an invited employee, links them to the company, and clears the token', function () {
    $company = Company::factory()->create();
    $employee = Employee::create([
        'company_id' => $company->id,
        'name' => 'Jane Employee',
        'email' => 'jane@acme.com',
        'invite_token' => 'test-token-uuid',
        'invite_sent_at' => now(),
    ]);

    $this->postJson('/api/auth/register', [
        'name' => 'Jane Employee',
        'email' => 'jane@acme.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'invite_token' => 'test-token-uuid',
    ])->assertCreated()
        ->assertJsonPath('data.company_id', $company->id)
        ->assertJsonPath('data.onboarding_step', 6);

    $user = User::withoutGlobalScope('company')->where('email', 'jane@acme.com')->first();
    expect($user->company_id)->toBe($company->id);
    expect($user->onboarding_step)->toBe(6);

    $employee->refresh();
    expect($employee->user_id)->toBe($user->id);
    expect($employee->invite_token)->toBeNull();
    expect($employee->invite_sent_at)->toBeNull();
});

it('fails registration with an invalid invite token and does not create a user', function () {
    $this->postJson('/api/auth/register', [
        'name' => 'Ghost User',
        'email' => 'ghost@acme.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'invite_token' => 'non-existent-token',
    ])->assertUnprocessable();

    expect(User::withoutGlobalScope('company')->count())->toBe(0);
});

it('fails registration when the invite token has already been claimed', function () {
    $company = Company::factory()->create();
    $existingUser = User::factory()->create(['company_id' => $company->id]);
    Employee::create([
        'company_id' => $company->id,
        'user_id' => $existingUser->id,
        'name' => 'Claimed Employee',
        'email' => 'claimed@acme.com',
        'invite_token' => 'already-used-token',
    ]);

    $this->postJson('/api/auth/register', [
        'name' => 'Attacker',
        'email' => 'attacker@acme.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'invite_token' => 'already-used-token',
    ])->assertUnprocessable();

    expect(User::withoutGlobalScope('company')->where('email', 'attacker@acme.com')->exists())->toBeFalse();
});

it('fails registration with duplicate email', function () {
    $company = Company::factory()->create();
    User::factory()->create([
        'company_id' => $company->id,
        'email' => 'existing@acme.com',
    ]);

    $this->postJson('/api/auth/register', [
        'name' => 'Jane',
        'email' => 'existing@acme.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});
