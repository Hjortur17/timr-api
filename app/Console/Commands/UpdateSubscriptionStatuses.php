<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Console\Command;

class UpdateSubscriptionStatuses extends Command
{
    /** Days a past-due subscription is retried before it fully expires. */
    public const DUNNING_DAYS = 7;

    protected $signature = 'subscriptions:tick';

    protected $description = 'Advance subscription lifecycle: expire lapsed trials, finalise cancellations, expire long past-due.';

    public function handle(): int
    {
        // Trials whose grace window has elapsed → expired.
        $expiredTrials = Subscription::query()
            ->where('status', SubscriptionStatus::Trialing)
            ->whereNotNull('grace_ends_at')
            ->where('grace_ends_at', '<=', now())
            ->update(['status' => SubscriptionStatus::Expired]);

        // Cancel-at-period-end: canceled-but-active subscriptions whose paid period
        // has now ended → canceled (access finally revoked).
        $finalisedCancellations = Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->whereNotNull('canceled_at')
            ->whereNotNull('current_period_ends_at')
            ->where('current_period_ends_at', '<=', now())
            ->update(['status' => SubscriptionStatus::Canceled]);

        // Past-due beyond the dunning window → expired.
        $expiredPastDue = Subscription::query()
            ->where('status', SubscriptionStatus::PastDue)
            ->whereNotNull('current_period_ends_at')
            ->where('current_period_ends_at', '<=', now()->subDays(self::DUNNING_DAYS))
            ->update(['status' => SubscriptionStatus::Expired]);

        $this->info("Expired {$expiredTrials} trial(s), finalised {$finalisedCancellations} cancellation(s), expired {$expiredPastDue} past-due.");

        return self::SUCCESS;
    }
}
