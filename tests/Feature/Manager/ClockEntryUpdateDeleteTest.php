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

describe('store', function () {
    it('allows a manager to create a clock entry', function () {
        $this->postJson('/api/manager/clock-entries', [
            'employee_id' => $this->employee->id,
            'clocked_in_at' => '2026-03-15 08:00:00',
            'clocked_out_at' => '2026-03-15 16:00:00',
        ])
            ->assertCreated()
            ->assertJsonPath('data.employee_id', $this->employee->id)
            ->assertJsonPath('data.clocked_in_at', '2026-03-15T08:00:00.000000Z')
            ->assertJsonPath('data.clocked_out_at', '2026-03-15T16:00:00.000000Z')
            ->assertJsonPath('data.is_extra', true);

        $this->assertDatabaseHas('clock_entries', [
            'employee_id' => $this->employee->id,
            'clocked_in_at' => '2026-03-15 08:00:00',
            'clocked_out_at' => '2026-03-15 16:00:00',
            'shift_id' => null,
        ]);
    });

    it('allows creating a clock entry without clocked_out_at', function () {
        $this->postJson('/api/manager/clock-entries', [
            'employee_id' => $this->employee->id,
            'clocked_in_at' => '2026-03-15 08:00:00',
        ])
            ->assertCreated()
            ->assertJsonPath('data.clocked_out_at', null);
    });

    it('validates employee_id is required', function () {
        $this->postJson('/api/manager/clock-entries', [
            'clocked_in_at' => '2026-03-15 08:00:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id']);
    });

    it('validates clocked_in_at is required', function () {
        $this->postJson('/api/manager/clock-entries', [
            'employee_id' => $this->employee->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['clocked_in_at']);
    });

    it('validates clocked_out_at is after clocked_in_at', function () {
        $this->postJson('/api/manager/clock-entries', [
            'employee_id' => $this->employee->id,
            'clocked_in_at' => '2026-03-15 16:00:00',
            'clocked_out_at' => '2026-03-15 08:00:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['clocked_out_at']);
    });

    it('prevents a non-manager from creating clock entries', function () {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $user->companies()->attach($this->company, ['role' => 'accountant']);
        $this->actingAs($user);

        $this->postJson('/api/manager/clock-entries', [
            'employee_id' => $this->employee->id,
            'clocked_in_at' => '2026-03-15 08:00:00',
        ])
            ->assertForbidden();
    });
});

describe('update', function () {
    it('allows a manager to update a clock entry', function () {
        $entry = ClockEntry::factory()->clockedOut()->create([
            'shift_id' => $this->shift->id,
            'employee_id' => $this->employee->id,
            'clocked_in_at' => '2026-03-15 08:00:00',
            'clocked_out_at' => '2026-03-15 16:00:00',
        ]);

        $this->putJson("/api/manager/clock-entries/{$entry->id}", [
            'clocked_in_at' => '2026-03-15 09:00:00',
            'clocked_out_at' => '2026-03-15 17:00:00',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $entry->id)
            ->assertJsonPath('data.clocked_in_at', '2026-03-15T09:00:00.000000Z')
            ->assertJsonPath('data.clocked_out_at', '2026-03-15T17:00:00.000000Z');

        $this->assertDatabaseHas('clock_entries', [
            'id' => $entry->id,
            'clocked_in_at' => '2026-03-15 09:00:00',
            'clocked_out_at' => '2026-03-15 17:00:00',
        ]);
    });

    it('allows partial update of a clock entry', function () {
        $entry = ClockEntry::factory()->clockedOut()->create([
            'shift_id' => $this->shift->id,
            'employee_id' => $this->employee->id,
            'clocked_in_at' => '2026-03-15 08:00:00',
            'clocked_out_at' => '2026-03-15 16:00:00',
        ]);

        $this->putJson("/api/manager/clock-entries/{$entry->id}", [
            'clocked_in_at' => '2026-03-15 10:00:00',
        ])
            ->assertOk();

        $this->assertDatabaseHas('clock_entries', [
            'id' => $entry->id,
            'clocked_in_at' => '2026-03-15 10:00:00',
            'clocked_out_at' => '2026-03-15 16:00:00',
        ]);
    });

    it('validates clocked_out_at is after clocked_in_at', function () {
        $entry = ClockEntry::factory()->clockedOut()->create([
            'shift_id' => $this->shift->id,
            'employee_id' => $this->employee->id,
            'clocked_in_at' => '2026-03-15 08:00:00',
            'clocked_out_at' => '2026-03-15 16:00:00',
        ]);

        $this->putJson("/api/manager/clock-entries/{$entry->id}", [
            'clocked_in_at' => '2026-03-15 18:00:00',
            'clocked_out_at' => '2026-03-15 10:00:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['clocked_out_at']);
    });

    it('cannot update a clock entry from another company', function () {
        $otherCompany = Company::factory()->create();
        $otherEmployee = Employee::factory()->create(['company_id' => $otherCompany->id]);
        $otherShift = Shift::factory()->create(['company_id' => $otherCompany->id]);

        $entry = ClockEntry::factory()->clockedOut()->create([
            'shift_id' => $otherShift->id,
            'employee_id' => $otherEmployee->id,
        ]);

        $this->putJson("/api/manager/clock-entries/{$entry->id}", [
            'clocked_in_at' => '2026-03-15 09:00:00',
        ])
            ->assertNotFound();
    });

    it('prevents a non-manager from updating clock entries', function () {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $user->companies()->attach($this->company, ['role' => 'accountant']);
        $this->actingAs($user);

        $entry = ClockEntry::factory()->clockedOut()->create([
            'shift_id' => $this->shift->id,
            'employee_id' => $this->employee->id,
        ]);

        $this->putJson("/api/manager/clock-entries/{$entry->id}", [
            'clocked_in_at' => '2026-03-15 09:00:00',
        ])
            ->assertForbidden();
    });
});

describe('delete', function () {
    it('allows a manager to soft delete a clock entry', function () {
        $entry = ClockEntry::factory()->clockedOut()->create([
            'shift_id' => $this->shift->id,
            'employee_id' => $this->employee->id,
        ]);

        $this->deleteJson("/api/manager/clock-entries/{$entry->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Clock entry deleted.');

        $this->assertSoftDeleted('clock_entries', ['id' => $entry->id]);
    });

    it('cannot delete a clock entry from another company', function () {
        $otherCompany = Company::factory()->create();
        $otherEmployee = Employee::factory()->create(['company_id' => $otherCompany->id]);
        $otherShift = Shift::factory()->create(['company_id' => $otherCompany->id]);

        $entry = ClockEntry::factory()->clockedOut()->create([
            'shift_id' => $otherShift->id,
            'employee_id' => $otherEmployee->id,
        ]);

        $this->deleteJson("/api/manager/clock-entries/{$entry->id}")
            ->assertNotFound();
    });

    it('prevents a non-manager from deleting clock entries', function () {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $user->companies()->attach($this->company, ['role' => 'accountant']);
        $this->actingAs($user);

        $entry = ClockEntry::factory()->clockedOut()->create([
            'shift_id' => $this->shift->id,
            'employee_id' => $this->employee->id,
        ]);

        $this->deleteJson("/api/manager/clock-entries/{$entry->id}")
            ->assertForbidden();
    });

    it('does not return soft deleted entries in the index', function () {
        $entry = ClockEntry::factory()->clockedOut()->create([
            'shift_id' => $this->shift->id,
            'employee_id' => $this->employee->id,
        ]);

        $entry->delete();

        $this->getJson('/api/manager/clock-entries')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });

    it('does not count soft deleted entries in summary', function () {
        ClockEntry::factory()->clockedOut()->create([
            'shift_id' => $this->shift->id,
            'employee_id' => $this->employee->id,
            'clocked_in_at' => '2026-03-15 08:00:00',
            'clocked_out_at' => '2026-03-15 16:00:00',
        ]);

        $deleted = ClockEntry::factory()->clockedOut()->create([
            'shift_id' => $this->shift->id,
            'employee_id' => $this->employee->id,
            'clocked_in_at' => '2026-03-16 08:00:00',
            'clocked_out_at' => '2026-03-16 16:00:00',
        ]);

        $deleted->delete();

        $this->getJson('/api/manager/clock-entries/summary')
            ->assertOk()
            ->assertJsonPath('data.0.entry_count', 1);
    });
});
