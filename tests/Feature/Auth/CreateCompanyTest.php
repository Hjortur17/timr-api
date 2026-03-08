<?php

use App\Models\Company;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
});

it('creates a company for a user without one', function () {
    $user = User::withoutGlobalScope('company')->create([
        'name' => 'John',
        'email' => 'john@example.com',
        'password' => bcrypt('password123'),
    ]);

    $response = $this->actingAs($user)->postJson('/api/auth/company', [
        'name' => 'Acme Corp',
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Company created successfully.')
        ->assertJsonPath('data.name', 'John');

    $user->refresh();
    expect($user->company_id)->not->toBeNull();
    expect($user->hasRole('manager'))->toBeTrue();
    expect(Company::count())->toBe(1);
    expect(Company::first()->name)->toBe('Acme Corp');
});

it('rejects company creation when user already has a company', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);

    $this->actingAs($user)->postJson('/api/auth/company', [
        'name' => 'Another Corp',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['company']);
});

it('fails company creation with missing name', function () {
    $user = User::withoutGlobalScope('company')->create([
        'name' => 'John',
        'email' => 'john@example.com',
        'password' => bcrypt('password123'),
    ]);

    $this->actingAs($user)->postJson('/api/auth/company', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('requires authentication to create a company', function () {
    $this->postJson('/api/auth/company', [
        'name' => 'Acme Corp',
    ])->assertUnauthorized();
});
