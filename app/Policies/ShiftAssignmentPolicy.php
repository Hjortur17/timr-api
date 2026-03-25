<?php

namespace App\Policies;

use App\Models\EmployeeShift;
use App\Models\User;

class ShiftAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isManager();
    }

    public function view(User $user, EmployeeShift $assignment): bool
    {
        return $user->isManager();
    }

    public function create(User $user): bool
    {
        return $user->isManager();
    }

    public function update(User $user, EmployeeShift $assignment): bool
    {
        return $user->isManager();
    }

    public function delete(User $user, EmployeeShift $assignment): bool
    {
        return $user->isManager();
    }
}
