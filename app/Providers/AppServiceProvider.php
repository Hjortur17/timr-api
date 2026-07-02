<?php

namespace App\Providers;

use App\Models\EmployeeShift;
use App\Policies\ShiftAssignmentPolicy;
use App\Services\Billing\BillingProvider;
use App\Services\Billing\Verifone\VerifoneClient;
use App\Services\Billing\VerifoneBillingProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The app depends only on the BillingProvider contract; swap this binding
        // when the Verifone integration is filled in.
        $this->app->bind(BillingProvider::class, VerifoneBillingProvider::class);

        // Singleton so the cached OAuth token is reused across a request lifecycle.
        $this->app->singleton(VerifoneClient::class);
    }

    public function boot(): void
    {
        Gate::policy(EmployeeShift::class, ShiftAssignmentPolicy::class);

        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('apple', \SocialiteProviders\Apple\Provider::class);
        });
    }
}
