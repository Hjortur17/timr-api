<?php

use App\Models\ClockEntry;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->manager = User::factory()->create([
        'company_id' => $this->company->id,
    ]);
    $this->manager->companies()->attach($this->company, ['role' => 'owner']);
    $this->actingAs($this->manager);

    $this->shift = Shift::factory()->create(['company_id' => $this->company->id]);
    $this->employee = Employee::factory()->create(['company_id' => $this->company->id]);
});

it('allows a manager to list clock entries', function () {
    ClockEntry::factory()->clockedOut()->count(3)->create([
        'shift_id' => $this->shift->id,
        'employee_id' => $this->employee->id,
    ]);

    $this->getJson('/api/manager/clock-entries')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('filters clock entries by employee_id', function () {
    $otherEmployee = Employee::factory()->create(['company_id' => $this->company->id]);

    ClockEntry::factory()->clockedOut()->count(2)->create([
        'shift_id' => $this->shift->id,
        'employee_id' => $this->employee->id,
    ]);
    ClockEntry::factory()->clockedOut()->create([
        'shift_id' => $this->shift->id,
        'employee_id' => $otherEmployee->id,
    ]);

    $this->getJson("/api/manager/clock-entries?employee_id={$this->employee->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('filters clock entries by date range', function () {
    ClockEntry::factory()->clockedOut()->create([
        'shift_id' => $this->shift->id,
        'employee_id' => $this->employee->id,
        'clocked_in_at' => '2026-03-15 08:00:00',
        'clocked_out_at' => '2026-03-15 16:00:00',
    ]);
    ClockEntry::factory()->clockedOut()->create([
        'shift_id' => $this->shift->id,
        'employee_id' => $this->employee->id,
        'clocked_in_at' => '2026-03-25 08:00:00',
        'clocked_out_at' => '2026-03-25 16:00:00',
    ]);

    $this->getJson('/api/manager/clock-entries?from=2026-03-01&to=2026-03-20')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('includes employee and shift data in clock entry response', function () {
    ClockEntry::factory()->clockedOut()->create([
        'shift_id' => $this->shift->id,
        'employee_id' => $this->employee->id,
    ]);

    $this->getJson('/api/manager/clock-entries')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'clocked_in_at',
                    'clocked_out_at',
                    'total_hours',
                    'employee' => ['id', 'name', 'email'],
                    'shift' => ['id', 'title'],
                ],
            ],
        ]);
});

it('returns clock entry summary grouped by employee', function () {
    ClockEntry::factory()->create([
        'shift_id' => $this->shift->id,
        'employee_id' => $this->employee->id,
        'clocked_in_at' => '2026-03-15 08:00:00',
        'clocked_out_at' => '2026-03-15 16:00:00',
    ]);
    ClockEntry::factory()->create([
        'shift_id' => $this->shift->id,
        'employee_id' => $this->employee->id,
        'clocked_in_at' => '2026-03-16 08:00:00',
        'clocked_out_at' => '2026-03-16 12:00:00',
    ]);

    $this->getJson('/api/manager/clock-entries/summary')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonStructure([
            'data' => [
                [
                    'employee' => ['id', 'name', 'email'],
                    'total_hours',
                    'entry_count',
                    'last_clocked_in_at',
                ],
            ],
        ]);
});

it('does not list clock entries from another company', function () {
    $otherCompany = Company::factory()->create();
    $otherShift = Shift::factory()->create(['company_id' => $otherCompany->id]);
    $otherEmployee = Employee::factory()->create(['company_id' => $otherCompany->id]);

    ClockEntry::factory()->clockedOut()->create([
        'shift_id' => $otherShift->id,
        'employee_id' => $otherEmployee->id,
    ]);

    $this->getJson('/api/manager/clock-entries')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('prevents a non-manager from accessing clock entries', function () {
    $user = User::factory()->create(['company_id' => $this->company->id]);
    $user->companies()->attach($this->company, ['role' => 'accountant']);
    $this->actingAs($user);

    $this->getJson('/api/manager/clock-entries')->assertForbidden();
});

it('prevents a non-manager from accessing clock entry summary', function () {
    $user = User::factory()->create(['company_id' => $this->company->id]);
    $user->companies()->attach($this->company, ['role' => 'accountant']);
    $this->actingAs($user);

    $this->getJson('/api/manager/clock-entries/summary')->assertForbidden();
});
