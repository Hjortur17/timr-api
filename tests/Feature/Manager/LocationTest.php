<?php

use App\Models\Company;
use App\Models\Location;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->manager = User::factory()->create([
        'company_id' => $this->company->id,
    ]);
    $this->manager->assignRole('manager');
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
        ->assertJsonValidationErrors(['name', 'latitude', 'longitude', 'geo_fence_radius']);
});

it('does not list locations from another company', function () {
    $otherCompany = Company::factory()->create();
    Location::factory()->create(['company_id' => $otherCompany->id]);

    $this->getJson('/api/manager/locations')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
