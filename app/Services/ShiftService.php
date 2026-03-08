<?php

namespace App\Services;

use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ShiftService
{
    public function listForCompany(): Collection
    {
        return Shift::query()
            ->with('employees')
            ->latest('start_time')
            ->get();
    }

    public function listForEmployee(User $employee): Collection
    {
        return Shift::query()
            ->whereHas('employees', fn ($q) => $q->where('users.id', $employee->id))
            ->where('status', 'published')
            ->latest('start_time')
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
}
