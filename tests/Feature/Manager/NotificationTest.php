<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\NotificationPreference;
use App\Models\Shift;
use App\Models\User;
use App\Notifications\ShiftChangedNotification;
use App\Notifications\ShiftPublishedNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->manager = User::factory()->create(['company_id' => $this->company->id]);
    $this->manager->companies()->attach($this->company, ['role' => 'owner']);
    $this->actingAs($this->manager);

    Notification::fake();
});

// ── Publish notifications ────────────────────────────────────────────

it('sends a batched ShiftPublishedNotification per employee when shifts are published', function () {
    $shift = Shift::factory()->create(['company_id' => $this->company->id]);
    $employeeA = Employee::factory()->create(['company_id' => $this->company->id, 'is_active' => true]);
    $employeeB = Employee::factory()->create(['company_id' => $this->company->id, 'is_active' => true]);

    EmployeeShift::factory()->create([
        'shift_id' => $shift->id,
        'employee_id' => $employeeA->id,
        'date' => '2026-04-01',
        'published' => false,
    ]);
    EmployeeShift::factory()->create([
        'shift_id' => $shift->id,
        'employee_id' => $employeeB->id,
        'date' => '2026-04-02',
        'published' => false,
    ]);

    $this->postJson('/api/manager/shifts/publish', [
        'from' => '2026-04-01',
        'to' => '2026-04-07',
    ])->assertOk();

    Notification::assertSentTo($employeeA, ShiftPublishedNotification::class);
    Notification::assertSentTo($employeeB, ShiftPublishedNotification::class);
    Notification::assertCount(2);
});

it('sends one batched email per employee even with multiple shifts', function () {
    $shiftA = Shift::factory()->create(['company_id' => $this->company->id]);
    $shiftB = Shift::factory()->create(['company_id' => $this->company->id]);
    $employee = Employee::factory()->create(['company_id' => $this->company->id, 'is_active' => true]);

    EmployeeShift::factory()->create([
        'shift_id' => $shiftA->id,
        'employee_id' => $employee->id,
        'date' => '2026-04-01',
        'published' => false,
    ]);
    EmployeeShift::factory()->create([
        'shift_id' => $shiftB->id,
        'employee_id' => $employee->id,
        'date' => '2026-04-02',
        'published' => false,
    ]);

    $this->postJson('/api/manager/shifts/publish', [
        'from' => '2026-04-01',
        'to' => '2026-04-07',
    ])->assertOk();

    // Only 1 notification for the employee (batched), not 2
    Notification::assertSentTo($employee, ShiftPublishedNotification::class, function ($notification) {
        return $notification->assignments->count() === 2;
    });
    Notification::assertCount(1);
});

it('does not send a notification when employee has disabled shift_published', function () {
    $shift = Shift::factory()->create(['company_id' => $this->company->id]);
    $user = User::factory()->create(['company_id' => $this->company->id]);
    $employee = Employee::factory()->create(['company_id' => $this->company->id, 'user_id' => $user->id, 'is_active' => true]);

    NotificationPreference::create([
        'user_id' => $user->id,
        'notification_type' => 'shift_published',
        'channel_push' => false,
        'channel_email' => false,
        'channel_in_app' => false,
    ]);

    EmployeeShift::factory()->create([
        'shift_id' => $shift->id,
        'employee_id' => $employee->id,
        'date' => '2026-04-01',
        'published' => false,
    ]);

    $this->postJson('/api/manager/shifts/publish', [
        'from' => '2026-04-01',
        'to' => '2026-04-07',
    ])->assertOk();

    Notification::assertNothingSent();
});

// ── Change notifications ─────────────────────────────────────────────

it('does not send ShiftChangedNotification when a published assignment is moved', function () {
    $shift = Shift::factory()->create(['company_id' => $this->company->id]);
    $employee = Employee::factory()->create(['company_id' => $this->company->id, 'is_active' => true]);

    $assignment = EmployeeShift::factory()->published()->create([
        'shift_id' => $shift->id,
        'employee_id' => $employee->id,
        'date' => '2026-04-01',
    ]);

    $this->putJson("/api/manager/shift-assignments/{$assignment->id}", [
        'date' => '2026-04-02',
    ])->assertOk();

    Notification::assertNothingSent();
});

it('does not send ShiftChangedNotification when an unpublished assignment is updated', function () {
    $shift = Shift::factory()->create(['company_id' => $this->company->id]);
    $employee = Employee::factory()->create(['company_id' => $this->company->id, 'is_active' => true]);

    $assignment = EmployeeShift::factory()->create([
        'shift_id' => $shift->id,
        'employee_id' => $employee->id,
        'date' => '2026-04-01',
        'published' => false,
    ]);

    $this->putJson("/api/manager/shift-assignments/{$assignment->id}", [
        'date' => '2026-04-02',
    ])->assertOk();

    Notification::assertNothingSent();
});

it('sends ShiftChangedNotification when a published assignment is deleted', function () {
    $shift = Shift::factory()->create(['company_id' => $this->company->id]);
    $employee = Employee::factory()->create(['company_id' => $this->company->id, 'is_active' => true]);

    $assignment = EmployeeShift::factory()->published()->create([
        'shift_id' => $shift->id,
        'employee_id' => $employee->id,
        'date' => '2026-04-01',
    ]);

    $this->deleteJson("/api/manager/shift-assignments/{$assignment->id}")
        ->assertOk();

    Notification::assertSentTo($employee, ShiftChangedNotification::class, function ($notification) {
        return $notification->changeType === 'deleted';
    });
});

it('does not send ShiftChangedNotification when employee has disabled schedule_change_alert', function () {
    $shift = Shift::factory()->create(['company_id' => $this->company->id]);
    $user = User::factory()->create(['company_id' => $this->company->id]);
    $employee = Employee::factory()->create(['company_id' => $this->company->id, 'user_id' => $user->id, 'is_active' => true]);

    NotificationPreference::create([
        'user_id' => $user->id,
        'notification_type' => 'schedule_change_alert',
        'channel_push' => false,
        'channel_email' => false,
        'channel_in_app' => false,
    ]);

    $assignment = EmployeeShift::factory()->published()->create([
        'shift_id' => $shift->id,
        'employee_id' => $employee->id,
        'date' => '2026-04-01',
    ]);

    $this->deleteJson("/api/manager/shift-assignments/{$assignment->id}")->assertOk();

    Notification::assertNothingSent();
});
