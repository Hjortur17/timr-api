<?php

use App\Models\Company;
use App\Models\Shift;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->manager = User::factory()->create([
        'company_id' => $this->company->id,
    ]);
    $this->manager->companies()->attach($this->company, ['role' => 'owner']);
    $this->actingAs($this->manager);
});

it('allows a manager to list shifts', function () {
    Shift::factory()->count(3)->create([
        'company_id' => $this->company->id,
    ]);

    $this->getJson('/api/manager/shifts')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('allows a manager to create a shift', function () {
    $response = $this->postJson('/api/manager/shifts', [
        'title' => 'Morning Shift',
        'start_time' => '08:00',
        'end_time' => '16:00',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Morning Shift');

    expect(Shift::count())->toBe(1);
});

it('allows a manager to update a shift', function () {
    $shift = Shift::factory()->create([
        'company_id' => $this->company->id,
    ]);

    $this->putJson("/api/manager/shifts/{$shift->id}", [
        'title' => 'Updated Shift',
    ])->assertOk()
        ->assertJsonPath('data.title', 'Updated Shift');
});

it('allows a manager to delete a shift', function () {
    $shift = Shift::factory()->create([
        'company_id' => $this->company->id,
    ]);

    $this->deleteJson("/api/manager/shifts/{$shift->id}")
        ->assertOk();

    expect(Shift::count())->toBe(0);
});

it('prevents a manager from seeing another companys shifts', function () {
    $otherCompany = Company::factory()->create();
    $otherShift = Shift::factory()->create([
        'company_id' => $otherCompany->id,
    ]);

    $this->putJson("/api/manager/shifts/{$otherShift->id}", [
        'title' => 'Hack',
    ])->assertNotFound();
});

it('prevents a non-manager from creating a shift', function () {
    $user = User::factory()->create(['company_id' => $this->company->id]);
    $user->companies()->attach($this->company, ['role' => 'accountant']);
    $this->actingAs($user);

    $this->postJson('/api/manager/shifts', [])->assertForbidden();
});
