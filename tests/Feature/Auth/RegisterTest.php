<?php

use App\Models\Company;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
});

it('registers a new company and manager', function () {
    $response = $this->postJson('/api/auth/register', [
        'company_name' => 'Acme Corp',
        'name' => 'John Manager',
        'email' => 'john@acme.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'John Manager')
        ->assertJsonPath('data.email', 'john@acme.com')
        ->assertJsonStructure(['data', 'token', 'message']);

    expect(Company::count())->toBe(1);
    expect(User::withoutGlobalScope('company')->count())->toBe(1);

    $user = User::withoutGlobalScope('company')->first();
    expect($user->hasRole('manager'))->toBeTrue();
});

it('fails registration with missing fields', function () {
    $this->postJson('/api/auth/register', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['company_name', 'name', 'email', 'password']);
});

it('fails registration with duplicate email', function () {
    $company = Company::factory()->create();
    User::factory()->create([
        'company_id' => $company->id,
        'email' => 'existing@acme.com',
    ]);

    $this->postJson('/api/auth/register', [
        'company_name' => 'New Corp',
        'name' => 'Jane',
        'email' => 'existing@acme.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});
