<?php

namespace App\Policies;

use App\Models\ShiftTemplate;
use App\Models\User;

class ShiftTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isManager();
    }

    public function view(User $user, ShiftTemplate $template): bool
    {
        return $user->company_id === $template->company_id;
    }

    public function create(User $user): bool
    {
        return $user->isManager();
    }

    public function update(User $user, ShiftTemplate $template): bool
    {
        return $user->company_id === $template->company_id
            && $user->isManager();
    }

    public function delete(User $user, ShiftTemplate $template): bool
    {
        return $user->company_id === $template->company_id
            && $user->isManager();
    }

    public function generate(User $user, ShiftTemplate $template): bool
    {
        return $user->company_id === $template->company_id
            && $user->isManager();
    }
}
