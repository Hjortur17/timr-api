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
            ->with('employee')
            ->latest('start_time')
            ->get();
    }

    public function listForEmployee(User $employee): Collection
    {
        return Shift::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'published')
            ->latest('start_time')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Shift
    {
        return Shift::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Shift $shift, array $data): Shift
    {
        $shift->update($data);

        return $shift->fresh();
    }

    public function delete(Shift $shift): void
    {
        $shift->delete();
    }
}
