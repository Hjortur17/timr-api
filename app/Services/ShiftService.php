<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\Shift;
use App\Notifications\ShiftPublishedNotification;
use Illuminate\Database\Eloquent\Collection;

class ShiftService
{
    public function listForCompany(): Collection
    {
        return Shift::query()
            ->with('employees')
            ->oldest('start_time')
            ->get();
    }

    public function listAssignmentsForEmployee(Employee $employee, ?string $from = null, ?string $to = null): Collection
    {
        $query = EmployeeShift::query()
            ->with('shift')
            ->where('employee_id', $employee->id)
            ->where('published', true);

        if ($from) {
            $query->where('date', '>=', $from);
        }

        if ($to) {
            $query->where('date', '<=', $to);
        }

        return $query->oldest('date')->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Shift
    {
        $employeeIds = $data['employee_ids'] ?? [];
        unset($data['employee_ids']);

        $shift = Shift::create($data);

        if (! empty($employeeIds)) {
            $shift->employees()->attach($employeeIds);
        }

        return $shift->load('employees');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Shift $shift, array $data): Shift
    {
        $employeeIds = $data['employee_ids'] ?? null;
        unset($data['employee_ids']);

        $shift->update($data);

        if ($employeeIds !== null) {
            $shift->employees()->sync($employeeIds);
        }

        return $shift->fresh('employees');
    }

    public function delete(Shift $shift): void
    {
        $shift->delete();
    }

    public function publishAssignmentsInRange(string $from, string $to): int
    {
        $count = EmployeeShift::query()
            ->whereBetween('date', [$from, $to])
            ->update(['published' => true]);

        // Dispatch one batched email per affected employee
        $assignments = EmployeeShift::query()
            ->with(['shift', 'employee.notificationPreferences'])
            ->whereBetween('date', [$from, $to])
            ->where('published', true)
            ->get();

        $assignments
            ->groupBy('employee_id')
            ->each(function (Collection $employeeAssignments) {
                /** @var Employee $employee */
                $employee = $employeeAssignments->first()->employee;

                if ($employee && $employee->prefersNotification(NotificationType::ShiftPublished)) {
                    $employee->notify(new ShiftPublishedNotification($employeeAssignments));
                }
            });

        return $count;
    }
}
