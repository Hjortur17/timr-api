<?php

use App\Models\Company;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create([
        'company_id' => $this->company->id,
    ]);
    $this->user->companies()->attach($this->company, ['role' => 'owner']);
});

it('updates user name', function () {
    $this->actingAs($this->user)
        ->patchJson('/api/auth/user', [
            'name' => 'Nýtt Nafn',
        ])->assertOk()
        ->assertJsonPath('data.name', 'Nýtt Nafn');

    expect($this->user->fresh()->name)->toBe('Nýtt Nafn');
});

it('updates user email', function () {
    $this->actingAs($this->user)
        ->patchJson('/api/auth/user', [
            'email' => 'nyttnetfang@example.com',
        ])->assertOk()
        ->assertJsonPath('data.email', 'nyttnetfang@example.com');

    expect($this->user->fresh()->email)->toBe('nyttnetfang@example.com');
});

it('updates both name and email', function () {
    $this->actingAs($this->user)
        ->patchJson('/api/auth/user', [
            'name' => 'Nýtt Nafn',
            'email' => 'nyttnetfang@example.com',
        ])->assertOk()
        ->assertJsonPath('data.name', 'Nýtt Nafn')
        ->assertJsonPath('data.email', 'nyttnetfang@example.com');

    $user = $this->user->fresh();
    expect($user->name)->toBe('Nýtt Nafn');
    expect($user->email)->toBe('nyttnetfang@example.com');
});

it('returns user resource with companies', function () {
    $this->actingAs($this->user)
        ->patchJson('/api/auth/user', [
            'name' => 'Nýtt Nafn',
        ])->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'name', 'email', 'company_id', 'companies'],
        ]);
});

it('fails when name is empty', function () {
    $this->actingAs($this->user)
        ->patchJson('/api/auth/user', [
            'name' => '',
        ])->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('fails when email is invalid', function () {
    $this->actingAs($this->user)
        ->patchJson('/api/auth/user', [
            'email' => 'not-an-email',
        ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('fails when email is already taken', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->actingAs($this->user)
        ->patchJson('/api/auth/user', [
            'email' => 'taken@example.com',
        ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('allows keeping the same email', function () {
    $this->actingAs($this->user)
        ->patchJson('/api/auth/user', [
            'email' => $this->user->email,
        ])->assertOk();
});

it('updates user locale', function () {
    $this->actingAs($this->user)
        ->patchJson('/api/auth/user', [
            'locale' => 'en',
        ])->assertOk()
        ->assertJsonPath('data.locale', 'en');

    expect($this->user->fresh()->locale)->toBe('en');
});

it('fails when locale is invalid', function () {
    $this->actingAs($this->user)
        ->patchJson('/api/auth/user', [
            'locale' => 'fr',
        ])->assertUnprocessable()
        ->assertJsonValidationErrors(['locale']);
});

it('requires authentication', function () {
    $this->patchJson('/api/auth/user', [
        'name' => 'Nýtt Nafn',
    ])->assertUnauthorized();
});
