<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(['onboarding_step' => 1]);
    $this->actingAs($this->user);
});

it('advances the onboarding step', function () {
    $this->patchJson('/api/auth/onboarding', ['step' => 3])
        ->assertOk()
        ->assertJsonPath('data.onboarding_step', 3);

    expect($this->user->fresh()->onboarding_step)->toBe(3);
});

it('accepts the final completion step', function () {
    $this->patchJson('/api/auth/onboarding', ['step' => 6])
        ->assertOk()
        ->assertJsonPath('data.onboarding_step', 6);
});

it('rejects a step beyond completion', function () {
    $this->patchJson('/api/auth/onboarding', ['step' => 7])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['step']);
});
