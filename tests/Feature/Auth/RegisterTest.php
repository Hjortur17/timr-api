<?php

use App\Models\Company;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
});

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
    expect($user->hasRole('manager'))->toBeFalse();
});

it('fails registration with missing fields', function () {
    $this->postJson('/api/auth/register', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'password']);
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
