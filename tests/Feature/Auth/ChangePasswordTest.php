<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create([
        'company_id' => $this->company->id,
        'password' => bcrypt('password123'),
    ]);
    $this->user->companies()->attach($this->company, ['role' => 'owner']);
});

it('changes password with valid current password', function () {
    $this->actingAs($this->user)
        ->patchJson('/api/auth/password', [
            'current_password' => 'password123',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertOk()
        ->assertJson(['message' => 'Lykilorð hefur verið uppfært.']);

    $this->user->refresh();
    expect(Hash::check('newpassword123', $this->user->password))->toBeTrue();
});

it('fails when current password is wrong', function () {
    $this->actingAs($this->user)
        ->patchJson('/api/auth/password', [
            'current_password' => 'wrongpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertUnprocessable()
        ->assertJsonValidationErrors(['current_password']);
});

it('fails when new passwords do not match', function () {
    $this->actingAs($this->user)
        ->patchJson('/api/auth/password', [
            'current_password' => 'password123',
            'password' => 'newpassword123',
            'password_confirmation' => 'differentpassword',
        ])->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('fails when new password is too short', function () {
    $this->actingAs($this->user)
        ->patchJson('/api/auth/password', [
            'current_password' => 'password123',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('validates all fields are required', function () {
    $this->actingAs($this->user)
        ->patchJson('/api/auth/password', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['current_password', 'password']);
});

it('requires authentication', function () {
    $this->patchJson('/api/auth/password', [
        'current_password' => 'password123',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])->assertUnauthorized();
});

it('deletes all other tokens after password change', function () {
    $this->user->createToken('other-device');
    $currentToken = $this->user->createToken('current-device');
    expect($this->user->tokens()->count())->toBe(2);

    $this->withHeader('Authorization', 'Bearer '.$currentToken->plainTextToken)
        ->patchJson('/api/auth/password', [
            'current_password' => 'password123',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertOk();

    // The current token should remain, others deleted
    expect($this->user->tokens()->count())->toBe(1);
});
