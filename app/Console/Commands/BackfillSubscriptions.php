<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class BackfillSubscriptions extends Command
{
    protected $signature = 'subscriptions:backfill';

    protected $description = 'Give companies that predate the subscription system a Free-plan 30-day trial.';

    public function handle(SubscriptionService $subscriptions): int
    {
        $count = $subscriptions->backfillMissing();

        $this->info("Backfilled {$count} company subscription(s).");

        return self::SUCCESS;
    }
}
