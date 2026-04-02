<?php

use App\Models\Company;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create([
        'company_id' => $this->company->id,
        'password' => bcrypt('password123'),
    ]);
    $this->user->companies()->attach($this->company, ['role' => 'owner']);
});

it('sends a password reset link to a valid email', function () {
    Notification::fake();

    $this->postJson('/api/auth/forgot-password', [
        'email' => $this->user->email,
    ])->assertOk();

    Notification::assertSentTo($this->user, ResetPasswordNotification::class);
});

it('returns 200 even for non-existent email', function () {
    Notification::fake();

    $this->postJson('/api/auth/forgot-password', [
        'email' => 'nonexistent@example.com',
    ])->assertOk();

    Notification::assertNothingSent();
});

it('validates that email is required', function () {
    $this->postJson('/api/auth/forgot-password', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('throttles repeated requests', function () {
    Notification::fake();

    $this->postJson('/api/auth/forgot-password', [
        'email' => $this->user->email,
    ])->assertOk();

    $this->postJson('/api/auth/forgot-password', [
        'email' => $this->user->email,
    ])->assertStatus(429);
});
