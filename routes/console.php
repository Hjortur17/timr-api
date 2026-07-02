<?php

use App\Console\Commands\ChargeDueSubscriptions;
use App\Console\Commands\SendShiftReminders;
use App\Console\Commands\UpdateSubscriptionStatuses;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(SendShiftReminders::class)->hourly();
// Charge due subscriptions before advancing statuses so a fresh charge can lift a
// subscription out of past-due before the dunning window is evaluated.
Schedule::command(ChargeDueSubscriptions::class)->dailyAt('06:00')->withoutOverlapping();
Schedule::command(UpdateSubscriptionStatuses::class)->dailyAt('06:30');
