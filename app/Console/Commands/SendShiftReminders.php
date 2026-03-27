<?php

namespace App\Console\Commands;

use App\Enums\NotificationType;
use App\Models\EmployeeShift;
use App\Notifications\ShiftReminderNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendShiftReminders extends Command
{
    protected $signature = 'shifts:send-reminders
                            {--hours=24 : Hours before shift start to send reminder}';

    protected $description = "Send shift reminder notifications to employees ahead of their upcoming shifts.";

    public function handle(): int
    {
        $hours = (int) $this->option("hours");
        $target = Carbon::now()->addHours($hours);

        // Target date lives on employee_shift; target time lives on shifts.
        // Split them so each condition hits the right table.
        $targetDate = $target->toDateString();
        $windowStartTime = $target->copy()->subMinutes(30)->format("H:i:s");
        $windowEndTime = $target->copy()->addMinutes(30)->format("H:i:s");

        info(
            "Target date: $targetDate, window: $windowStartTime - $windowEndTime",
        );

        // Find published assignments starting in the reminder window that haven't been reminded yet
        $assignments = EmployeeShift::query()
            ->with(["shift", "employee.notificationPreferences"])
            ->where("published", true)
            ->whereNull("reminder_sent_at")
            ->where("date", $targetDate)
            ->whereHas("shift", function ($query) use (
                $windowStartTime,
                $windowEndTime,
            ) {
                $query->whereBetween("start_time", [
                    $windowStartTime,
                    $windowEndTime,
                ]);
            })
            ->get();

        $sent = 0;

        foreach ($assignments as $assignment) {
            $employee = $assignment->employee;

            if (
                !$employee ||
                !$employee->prefersNotification(NotificationType::ShiftReminder)
            ) {
                continue;
            }

            $employee->notify(new ShiftReminderNotification($assignment));

            $assignment->update(["reminder_sent_at" => now()]);

            $sent++;
        }

        $this->info("Sent {$sent} shift reminder(s).");

        return self::SUCCESS;
    }
}
