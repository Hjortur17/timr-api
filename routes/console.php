<?php

use App\Console\Commands\SendShiftReminders;
use App\Console\Commands\UpdateSubscriptionStatuses;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(SendShiftReminders::class)->hourly();
Schedule::command(UpdateSubscriptionStatuses::class)->daily();
