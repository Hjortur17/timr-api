<?php

use App\Console\Commands\SendShiftReminders;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\Shift;
use App\Models\User;
use App\Notifications\ShiftReminderNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

it('sends shift reminders for assignments starting in 24 hours', function () {
    $company = Company::factory()->create();
    $shift = Shift::factory()->create([
        'company_id' => $company->id,
        'start_time' => '09:00',
        'end_time' => '17:00',
    ]);
    $employee = Employee::factory()->create([
        'company_id' => $company->id,
        'is_active' => true,
    ]);

    // Assignment exactly 24 hours from now
    Carbon::setTestNow('2026-04-01 09:00:00');

    EmployeeShift::factory()->create([
        'shift_id' => $shift->id,
        'employee_id' => $employee->id,
        'date' => '2026-04-02',
        'published' => true,
        'reminder_sent_at' => null,
    ]);

    $this->artisan(SendShiftReminders::class)->assertSuccessful();

    Notification::assertSentTo($employee, ShiftReminderNotification::class);

    Carbon::setTestNow();
});

it('does not send reminder for unpublished assignments', function () {
    $company = Company::factory()->create();
    $shift = Shift::factory()->create([
        'company_id' => $company->id,
        'start_time' => '09:00',
        'end_time' => '17:00',
    ]);
    $employee = Employee::factory()->create([
        'company_id' => $company->id,
        'is_active' => true,
    ]);

    Carbon::setTestNow('2026-04-01 09:00:00');

    EmployeeShift::factory()->create([
        'shift_id' => $shift->id,
        'employee_id' => $employee->id,
        'date' => '2026-04-02',
        'published' => false,
        'reminder_sent_at' => null,
    ]);

    $this->artisan(SendShiftReminders::class)->assertSuccessful();

    Notification::assertNothingSent();

    Carbon::setTestNow();
});

it('does not send reminder if already sent', function () {
    $company = Company::factory()->create();
    $shift = Shift::factory()->create([
        'company_id' => $company->id,
        'start_time' => '09:00',
        'end_time' => '17:00',
    ]);
    $employee = Employee::factory()->create([
        'company_id' => $company->id,
        'is_active' => true,
    ]);

    Carbon::setTestNow('2026-04-01 09:00:00');

    EmployeeShift::factory()->create([
        'shift_id' => $shift->id,
        'employee_id' => $employee->id,
        'date' => '2026-04-02',
        'published' => true,
        'reminder_sent_at' => now()->subHour(),
    ]);

    $this->artisan(SendShiftReminders::class)->assertSuccessful();

    Notification::assertNothingSent();

    Carbon::setTestNow();
});

it('does not send reminder when employee has disabled shift_reminder', function () {
    $company = Company::factory()->create();
    $shift = Shift::factory()->create([
        'company_id' => $company->id,
        'start_time' => '09:00',
        'end_time' => '17:00',
    ]);
    $employee = Employee::factory()->create([
        'company_id' => $company->id,
        'is_active' => true,
    ]);

    $employee->notificationPreferences()->create([
        'type' => 'shift_reminder',
        'enabled' => false,
    ]);

    Carbon::setTestNow('2026-04-01 09:00:00');

    EmployeeShift::factory()->create([
        'shift_id' => $shift->id,
        'employee_id' => $employee->id,
        'date' => '2026-04-02',
        'published' => true,
        'reminder_sent_at' => null,
    ]);

    $this->artisan(SendShiftReminders::class)->assertSuccessful();

    Notification::assertNothingSent();

    Carbon::setTestNow();
});

it('marks reminder_sent_at after sending', function () {
    $company = Company::factory()->create();
    $shift = Shift::factory()->create([
        'company_id' => $company->id,
        'start_time' => '09:00',
        'end_time' => '17:00',
    ]);
    $employee = Employee::factory()->create([
        'company_id' => $company->id,
        'is_active' => true,
    ]);

    Carbon::setTestNow('2026-04-01 09:00:00');

    $assignment = EmployeeShift::factory()->create([
        'shift_id' => $shift->id,
        'employee_id' => $employee->id,
        'date' => '2026-04-02',
        'published' => true,
        'reminder_sent_at' => null,
    ]);

    $this->artisan(SendShiftReminders::class)->assertSuccessful();

    expect($assignment->fresh()->reminder_sent_at)->not->toBeNull();

    Carbon::setTestNow();
});
