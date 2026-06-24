<?php

use App\Models\Company;
use App\Models\Location;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->manager = User::factory()->create([
        'company_id' => $this->company->id,
    ]);
    $this->manager->companies()->attach($this->company, ['role' => 'owner']);
    $this->actingAs($this->manager);
});

it('allows a manager to list locations', function () {
    Location::factory()->count(2)->create([
        'company_id' => $this->company->id,
    ]);

    $this->getJson('/api/manager/locations')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('allows a manager to create a location', function () {
    $response = $this->postJson('/api/manager/locations', [
        'name' => 'Main Office',
        'address' => '123 Main St',
        'latitude' => 64.1355,
        'longitude' => -21.8954,
        'geo_fence_radius' => 200,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Main Office')
        ->assertJsonPath('data.geo_fence_radius', 200);

    expect(Location::count())->toBe(1);
});

it('validates location creation data', function () {
    $this->postJson('/api/manager/locations', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name'])
        ->assertJsonMissingValidationErrors(['latitude', 'longitude', 'geo_fence_radius']);
});

it('allows creating a workplace with GPS turned off', function () {
    $this->postJson('/api/manager/locations', [
        'name' => 'Remote',
        'address' => null,
    ])
        ->assertCreated()
        ->assertJsonPath('data.geo_fence_radius', null)
        ->assertJsonPath('data.opening_hours_mode', 'global')
        ->assertJsonPath('data.opening_hours', null);
});

it('stores and reads back custom opening hours', function () {
    $hours = [
        'days' => [true, true, true, true, true, false, false],
        'time_mode' => 'perday',
        'open' => '11:00',
        'close' => '14:00',
        'times' => array_fill(0, 7, ['open' => '11:00', 'close' => '14:00']),
        'exc' => [['date' => '2026-06-17', 'label' => 'Þjóðhátíð', 'mode' => 'closed', 'open' => null, 'close' => null]],
    ];

    $this->postJson('/api/manager/locations', [
        'name' => 'Skólavörðustígur 8',
        'opening_hours_mode' => 'custom',
        'opening_hours' => $hours,
    ])
        ->assertCreated()
        ->assertJsonPath('data.opening_hours_mode', 'custom')
        ->assertJsonPath('data.opening_hours.time_mode', 'perday')
        ->assertJsonPath('data.opening_hours.exc.0.date', '2026-06-17');
});

it('requires opening hours when mode is custom', function () {
    $this->postJson('/api/manager/locations', [
        'name' => 'Bad',
        'opening_hours_mode' => 'custom',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('opening_hours');
});

it('allows a manager to update a location', function () {
    $location = Location::factory()->create(['company_id' => $this->company->id, 'name' => 'Old']);

    $this->putJson("/api/manager/locations/{$location->id}", [
        'name' => 'New Name',
        'opening_hours_mode' => 'global',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.opening_hours', null);
});

it('allows a manager to delete a location', function () {
    $location = Location::factory()->create(['company_id' => $this->company->id]);

    $this->deleteJson("/api/manager/locations/{$location->id}")->assertOk();

    expect(Location::withoutGlobalScope('company')->count())->toBe(0);
});

it('does not allow updating a location from another company', function () {
    $otherCompany = Company::factory()->create();
    $location = Location::factory()->create(['company_id' => $otherCompany->id]);

    $this->putJson("/api/manager/locations/{$location->id}", ['name' => 'Hijack'])
        ->assertNotFound();
});

it('does not list locations from another company', function () {
    $otherCompany = Company::factory()->create();
    Location::factory()->create(['company_id' => $otherCompany->id]);

    $this->getJson('/api/manager/locations')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
