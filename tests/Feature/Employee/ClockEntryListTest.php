<?php

use App\Models\ClockEntry;
use App\Models\Company;
use App\Models\Employee;
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
    $this->shift = Shift::factory()->create(['company_id' => $this->company->id]);
});

it('allows an employee to list their own clock entries', function () {
    ClockEntry::factory()->clockedOut()->count(3)->create([
        'shift_id' => $this->shift->id,
        'employee_id' => $this->employee->id,
    ]);

    $this->getJson('/api/employee/clock-entries')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('does not include clock entries from other employees', function () {
    $otherEmployee = Employee::factory()->create(['company_id' => $this->company->id]);

    ClockEntry::factory()->clockedOut()->create([
        'shift_id' => $this->shift->id,
        'employee_id' => $this->employee->id,
    ]);
    ClockEntry::factory()->clockedOut()->create([
        'shift_id' => $this->shift->id,
        'employee_id' => $otherEmployee->id,
    ]);

    $this->getJson('/api/employee/clock-entries')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters employee clock entries by date range', function () {
    ClockEntry::factory()->create([
        'shift_id' => $this->shift->id,
        'employee_id' => $this->employee->id,
        'clocked_in_at' => '2026-03-15 08:00:00',
        'clocked_out_at' => '2026-03-15 16:00:00',
    ]);
    ClockEntry::factory()->create([
        'shift_id' => $this->shift->id,
        'employee_id' => $this->employee->id,
        'clocked_in_at' => '2026-03-25 08:00:00',
        'clocked_out_at' => '2026-03-25 16:00:00',
    ]);

    $this->getJson('/api/employee/clock-entries?from=2026-03-01&to=2026-03-20')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('includes total_hours and shift data in response', function () {
    ClockEntry::factory()->clockedOut()->create([
        'shift_id' => $this->shift->id,
        'employee_id' => $this->employee->id,
    ]);

    $this->getJson('/api/employee/clock-entries')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'clocked_in_at',
                    'clocked_out_at',
                    'total_hours',
                    'shift' => ['id', 'title'],
                ],
            ],
        ]);
});
