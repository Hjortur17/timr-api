<?php

namespace App\Providers;

use App\Models\EmployeeShift;
use App\Policies\ShiftAssignmentPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(EmployeeShift::class, ShiftAssignmentPolicy::class);
    }
}
