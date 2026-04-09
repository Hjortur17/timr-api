<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\Shift;
use App\Notifications\ShiftPublishedNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

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
            ->where('published_employee_id', $employee->id)
            ->where('published', true);

        if ($from) {
            $query->whereDate('published_date', '>=', $from);
        }

        if ($to) {
            $query->whereDate('published_date', '<=', $to);
        }

        return $query->oldest('published_date')->get();
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

    /**
     * @return array<string, mixed>
     */
    public function getDeletionPreview(Shift $shift): array
    {
        $today = now()->toDateString();

        $totalAssignments = $shift->assignments()->count();
        $totalEmployees = $shift->assignments()->distinct('employee_id')->count('employee_id');
        $futureAssignments = $shift->assignments()->where('date', '>=', $today)->count();
        $futureEmployees = $shift->assignments()->where('date', '>=', $today)->distinct('employee_id')->count('employee_id');

        $replacementShifts = Shift::query()
            ->where('id', '!=', $shift->id)
            ->select('id', 'title', 'start_time', 'end_time')
            ->oldest('start_time')
            ->get();

        return [
            'total_assignments' => $totalAssignments,
            'total_employees' => $totalEmployees,
            'future_assignments' => $futureAssignments,
            'future_employees' => $futureEmployees,
            'replacement_shifts' => $replacementShifts,
        ];
    }

    public function delete(Shift $shift, ?int $replacementShiftId = null): void
    {
        DB::transaction(function () use ($shift, $replacementShiftId) {
            $today = now()->toDateString();

            if ($replacementShiftId) {
                $futureAssignments = $shift->assignments()->where('date', '>=', $today)->get();

                foreach ($futureAssignments as $assignment) {
                    $duplicateExists = EmployeeShift::query()
                        ->where('shift_id', $replacementShiftId)
                        ->where('employee_id', $assignment->employee_id)
                        ->where('date', $assignment->date)
                        ->exists();

                    if ($duplicateExists) {
                        $assignment->delete();
                    } else {
                        $assignment->update(['shift_id' => $replacementShiftId]);
                    }
                }
            } else {
                $shift->assignments()->where('date', '>=', $today)->delete();
            }

            $shift->delete();
        });
    }

    public function publishAssignmentsInRange(?string $from = null, ?string $to = null): int
    {
        $query = EmployeeShift::query()
            ->where(function ($q) {
                $q->where('published', false)
                    ->orWhereColumn('date', '!=', 'published_date')
                    ->orWhereColumn('employee_id', '!=', 'published_employee_id');
            });

        if ($from && $to) {
            $query->whereBetween('date', [$from, $to]);
        }

        $assignments = $query->get();

        if ($assignments->isEmpty()) {
            return 0;
        }

        foreach ($assignments as $assignment) {
            $assignment->update([
                'published' => true,
                'published_date' => $assignment->date,
                'published_employee_id' => $assignment->employee_id,
            ]);
        }

        $count = $assignments->count();

        // Dispatch one batched email per affected employee
        $notificationQuery = EmployeeShift::query()
            ->with(['shift', 'employee.user.notificationPreferences'])
            ->where('published', true);

        if ($from && $to) {
            $notificationQuery->whereBetween('date', [$from, $to]);
        }

        $assignments = $notificationQuery->get();

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

    public function renderIcal(Collection $assignments): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Timr//Vaktir//IS',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:Vaktir',
        ];

        $now = now()->utc()->format('Ymd\THis\Z');

        foreach ($assignments as $assignment) {
            $shift = $assignment->shift;
            $startTime = str_replace(':', '', substr($shift->start_time, 0, 5)).'00';
            $endTime = str_replace(':', '', substr($shift->end_time, 0, 5)).'00';
            $dateFormatted = $assignment->published_date->format('Ymd');

            $uid = "timr-assignment-{$assignment->id}@timr.is";

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = "UID:{$uid}";
            $lines[] = "DTSTAMP:{$now}";
            $lines[] = "DTSTART:{$dateFormatted}T{$startTime}";
            $lines[] = "DTEND:{$dateFormatted}T{$endTime}";
            $lines[] = "SUMMARY:{$shift->title}";

            if ($shift->notes) {
                $lines[] = "DESCRIPTION:{$shift->notes}";
            }

            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines)."\r\n";
    }

    /**
     * @param  array<int>  $ids
     */
    public function unpublishAssignments(array $ids): int
    {
        return EmployeeShift::query()
            ->whereIn('id', $ids)
            ->update([
                'published' => false,
                'published_date' => null,
                'published_employee_id' => null,
            ]);
    }
}
