<?php

namespace App\Policies;

use App\Models\Shift;
use App\Models\User;

class ClockEntryPolicy
{
    public function clockIn(User $user, Shift $shift): bool
    {
        return $user->company_id === $shift->company_id
            && $shift->employee_id === $user->id;
    }

    public function clockOut(User $user): bool
    {
        return $user->hasRole('employee');
    }
}
