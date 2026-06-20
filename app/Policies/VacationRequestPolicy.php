<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VacationRequest;

class VacationRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isManager();
    }

    public function view(User $user, VacationRequest $vacationRequest): bool
    {
        if ($user->company_id !== $vacationRequest->company_id) {
            return false;
        }

        if ($user->isManager()) {
            return true;
        }

        return $vacationRequest->employee?->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->employee()->exists();
    }

    public function cancel(User $user, VacationRequest $vacationRequest): bool
    {
        return $user->company_id === $vacationRequest->company_id
            && $vacationRequest->employee?->user_id === $user->id;
    }

    public function review(User $user, VacationRequest $vacationRequest): bool
    {
        return $user->company_id === $vacationRequest->company_id
            && $user->isManager();
    }

    public function createForEmployee(User $user): bool
    {
        return $user->isManager();
    }

    public function update(User $user, VacationRequest $vacationRequest): bool
    {
        return $user->company_id === $vacationRequest->company_id
            && $user->isManager();
    }

    public function restore(User $user, VacationRequest $vacationRequest): bool
    {
        return $user->company_id === $vacationRequest->company_id
            && $user->isManager();
    }
}
