<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Console\Command;

class UpdateSubscriptionStatuses extends Command
{
    protected $signature = 'subscriptions:tick';

    protected $description = 'Mark trials whose grace window has elapsed as expired.';

    public function handle(): int
    {
        $expired = Subscription::query()
            ->where('status', SubscriptionStatus::Trialing)
            ->whereNotNull('grace_ends_at')
            ->where('grace_ends_at', '<=', now())
            ->update(['status' => SubscriptionStatus::Expired]);

        $this->info("Expired {$expired} subscription(s).");

        return self::SUCCESS;
    }
}
