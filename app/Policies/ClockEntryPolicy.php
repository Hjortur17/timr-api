<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\User;

class ClockEntryPolicy
{
    public function clockIn(User $user, ?Shift $shift = null): bool
    {
        $employee = Employee::query()
            ->withoutGlobalScope('company')
            ->where('user_id', $user->id)
            ->first();

        if (! $employee) {
            return false;
        }

        if (! $shift) {
            return true;
        }

        return $employee->company_id === $shift->company_id
            && $shift->employees()->where('employees.id', $employee->id)->exists();
    }

    public function clockOut(User $user): bool
    {
        return Employee::query()
            ->withoutGlobalScope('company')
            ->where('user_id', $user->id)
            ->exists();
    }
}
