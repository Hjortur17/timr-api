<?php

use App\Models\ClockEntry;
use App\Models\Company;
use App\Models\Location;
use App\Models\Shift;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->employee = User::factory()->create([
        'company_id' => $this->company->id,
    ]);
    $this->employee->assignRole('employee');
    $this->actingAs($this->employee);
    $this->location = Location::factory()->create([
        'company_id' => $this->company->id,
        'latitude' => 64.1355,
        'longitude' => -21.8954,
        'geo_fence_radius' => 200,
    ]);
});

it('allows an employee to clock in within geo fence', function () {
    $shift = Shift::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
    ]);

    $this->postJson('/api/employee/clock-in', [
        'shift_id' => $shift->id,
        'latitude' => 64.1356,
        'longitude' => -21.8955,
    ])->assertCreated();

    expect(ClockEntry::withoutGlobalScope('company')->count())->toBe(1);
});

it('rejects clock in when employee is outside geo fence', function () {
    $shift = Shift::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
    ]);

    $this->postJson('/api/employee/clock-in', [
        'shift_id' => $shift->id,
        'latitude' => 65.6835,
        'longitude' => -18.1002,
    ])->assertUnprocessable();
});

it('prevents clocking in to another employees shift', function () {
    $otherEmployee = User::factory()->create([
        'company_id' => $this->company->id,
    ]);
    $shift = Shift::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $otherEmployee->id,
    ]);

    $this->postJson('/api/employee/clock-in', [
        'shift_id' => $shift->id,
        'latitude' => 64.1356,
        'longitude' => -21.8955,
    ])->assertForbidden();
});

it('allows an employee to clock out', function () {
    $shift = Shift::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
    ]);

    ClockEntry::factory()->create([
        'shift_id' => $shift->id,
        'user_id' => $this->employee->id,
        'clocked_in_at' => now(),
        'clocked_out_at' => null,
    ]);

    $this->postJson('/api/employee/clock-out')
        ->assertOk();

    $entry = ClockEntry::withoutGlobalScope('company')->first();
    expect($entry->clocked_out_at)->not->toBeNull();
});

it('fails clock out when not clocked in', function () {
    $this->postJson('/api/employee/clock-out')
        ->assertUnprocessable();
});

it('prevents double clock in for the same shift', function () {
    $shift = Shift::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
    ]);

    ClockEntry::factory()->create([
        'shift_id' => $shift->id,
        'user_id' => $this->employee->id,
        'clocked_in_at' => now(),
        'clocked_out_at' => null,
    ]);

    $this->postJson('/api/employee/clock-in', [
        'shift_id' => $shift->id,
        'latitude' => 64.1356,
        'longitude' => -21.8955,
    ])->assertUnprocessable();
});
