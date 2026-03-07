<?php

namespace App\Policies;

use App\Models\Shift;
use App\Models\User;

class ShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('manager');
    }

    public function view(User $user, Shift $shift): bool
    {
        return $user->company_id === $shift->company_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('manager');
    }

    public function update(User $user, Shift $shift): bool
    {
        return $user->company_id === $shift->company_id
            && $user->hasRole('manager');
    }

    public function delete(User $user, Shift $shift): bool
    {
        return $user->company_id === $shift->company_id
            && $user->hasRole('manager');
    }
}
