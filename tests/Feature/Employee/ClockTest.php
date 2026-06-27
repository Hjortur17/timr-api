<?php

use App\Models\ClockEntry;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Shift;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create([
        'company_id' => $this->company->id,
    ]);
    $this->employee = Employee::create([
        'company_id' => $this->company->id,
        'user_id' => $this->user->id,
        'name' => $this->user->name,
    ]);
    $this->actingAs($this->user);
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
    ]);
    $shift->employees()->attach($this->employee, ['date' => today()->toDateString(), 'published' => true]);

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
    ]);
    $shift->employees()->attach($this->employee, ['date' => today()->toDateString(), 'published' => true]);

    $this->postJson('/api/employee/clock-in', [
        'shift_id' => $shift->id,
        'latitude' => 65.6835,
        'longitude' => -18.1002,
    ])->assertUnprocessable();
});

it('prevents clocking in to another employees shift', function () {
    $otherUser = User::factory()->create([
        'company_id' => $this->company->id,
    ]);
    $otherEmployee = Employee::create([
        'company_id' => $this->company->id,
        'user_id' => $otherUser->id,
        'name' => $otherUser->name,
    ]);
    $shift = Shift::factory()->create([
        'company_id' => $this->company->id,
    ]);
    $shift->employees()->attach($otherEmployee, ['date' => today()->toDateString(), 'published' => true]);

    $this->postJson('/api/employee/clock-in', [
        'shift_id' => $shift->id,
        'latitude' => 64.1356,
        'longitude' => -21.8955,
    ])->assertForbidden();
});

it('validates against the shift assigned workplace, not the nearest', function () {
    // A far-away second workplace the employee is actually standing next to.
    $faraway = Location::factory()->create([
        'company_id' => $this->company->id,
        'latitude' => 65.6835,
        'longitude' => -18.1002,
        'geo_fence_radius' => 200,
    ]);

    // The shift belongs to the Reykjavík workplace, but the employee is in Akureyri.
    $shift = Shift::factory()->create([
        'company_id' => $this->company->id,
        'location_id' => $this->location->id,
    ]);
    $shift->employees()->attach($this->employee, ['date' => today()->toDateString(), 'published' => true]);

    $this->postJson('/api/employee/clock-in', [
        'shift_id' => $shift->id,
        'latitude' => 65.6835,
        'longitude' => -18.1002,
    ])->assertUnprocessable();

    expect($faraway)->not->toBeNull(); // present only to prove it is NOT used
});

it('records the workplace on the clock entry', function () {
    $shift = Shift::factory()->create([
        'company_id' => $this->company->id,
        'location_id' => $this->location->id,
    ]);
    $shift->employees()->attach($this->employee, ['date' => today()->toDateString(), 'published' => true]);

    $this->postJson('/api/employee/clock-in', [
        'shift_id' => $shift->id,
        'latitude' => 64.1356,
        'longitude' => -21.8955,
    ])->assertCreated();

    $entry = ClockEntry::withoutGlobalScope('company')->first();
    expect($entry->location_id)->toBe($this->location->id);
});

it('clocks in at the shift workplace even when another workplace is far away', function () {
    // The original bug: an arbitrary other workplace must not gate this shift.
    Location::factory()->create([
        'company_id' => $this->company->id,
        'latitude' => 65.6835,
        'longitude' => -18.1002,
        'geo_fence_radius' => 200,
    ]);

    $shift = Shift::factory()->create([
        'company_id' => $this->company->id,
        'location_id' => $this->location->id,
    ]);
    $shift->employees()->attach($this->employee, ['date' => today()->toDateString(), 'published' => true]);

    $this->postJson('/api/employee/clock-in', [
        'shift_id' => $shift->id,
        'latitude' => 64.1356,
        'longitude' => -21.8955,
    ])->assertCreated();
});

it('skips the geo fence when the shift workplace has GPS off', function () {
    $noGps = Location::factory()->create([
        'company_id' => $this->company->id,
        'latitude' => null,
        'longitude' => null,
        'geo_fence_radius' => null,
    ]);
    $shift = Shift::factory()->create([
        'company_id' => $this->company->id,
        'location_id' => $noGps->id,
    ]);
    $shift->employees()->attach($this->employee, ['date' => today()->toDateString(), 'published' => true]);

    // Coordinates anywhere are accepted because the workplace imposes no fence.
    $this->postJson('/api/employee/clock-in', [
        'shift_id' => $shift->id,
        'latitude' => 65.6835,
        'longitude' => -18.1002,
    ])->assertCreated();

    expect(ClockEntry::withoutGlobalScope('company')->first()->location_id)->toBe($noGps->id);
});

it('allows an employee to clock out', function () {
    $shift = Shift::factory()->create([
        'company_id' => $this->company->id,
    ]);
    $shift->employees()->attach($this->employee, ['date' => today()->toDateString(), 'published' => true]);

    ClockEntry::create([
        'shift_id' => $shift->id,
        'employee_id' => $this->employee->id,
        'clocked_in_at' => now(),
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
    ]);
    $shift->employees()->attach($this->employee, ['date' => today()->toDateString(), 'published' => true]);

    ClockEntry::create([
        'shift_id' => $shift->id,
        'employee_id' => $this->employee->id,
        'clocked_in_at' => now(),
    ]);

    $this->postJson('/api/employee/clock-in', [
        'shift_id' => $shift->id,
        'latitude' => 64.1356,
        'longitude' => -21.8955,
    ])->assertUnprocessable();
});

it('allows an employee to clock in without a shift (extra)', function () {
    $this->postJson('/api/employee/clock-in', [
        'latitude' => 64.1356,
        'longitude' => -21.8955,
    ])->assertCreated();

    $entry = ClockEntry::withoutGlobalScope('company')->first();
    expect($entry->shift_id)->toBeNull();
    expect($entry->employee_id)->toBe($this->employee->id);
});

it('marks extra clock entry with is_extra in response', function () {
    $this->postJson('/api/employee/clock-in', [
        'latitude' => 64.1356,
        'longitude' => -21.8955,
    ])->assertCreated()
        ->assertJsonPath('data.is_extra', true)
        ->assertJsonPath('data.shift_id', null);
});

it('prevents double extra clock in', function () {
    ClockEntry::create([
        'shift_id' => null,
        'employee_id' => $this->employee->id,
        'clocked_in_at' => now(),
    ]);

    $this->postJson('/api/employee/clock-in', [
        'latitude' => 64.1356,
        'longitude' => -21.8955,
    ])->assertUnprocessable();
});

it('allows clock out from extra clock entry', function () {
    ClockEntry::create([
        'shift_id' => null,
        'employee_id' => $this->employee->id,
        'clocked_in_at' => now(),
    ]);

    $this->postJson('/api/employee/clock-out')
        ->assertOk();

    $entry = ClockEntry::withoutGlobalScope('company')->first();
    expect($entry->clocked_out_at)->not->toBeNull();
});
