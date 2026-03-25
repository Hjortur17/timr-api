<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\Shift;
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

    public function listForEmployee(Employee $employee): Collection
    {
        return Shift::query()
            ->whereHas('employees', fn ($q) => $q->where('employees.id', $employee->id))
            ->where('status', 'published')
            ->oldest('start_time')
            ->get();
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
        return EmployeeShift::query()
            ->whereBetween('date', [$from, $to])
            ->update(['published' => true]);
    }
}
