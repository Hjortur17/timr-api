<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Services\Billing\PaymentHandler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ChargeDueSubscriptions extends Command
{
    protected $signature = 'subscriptions:charge';

    protected $description = 'Run merchant-initiated recurring charges for subscriptions whose period is due.';

    public function handle(PaymentHandler $payments): int
    {
        $charged = 0;
        $declined = 0;

        $this->dueSubscriptions()->each(function (Subscription $subscription) use ($payments, &$charged, &$declined) {
            $result = $payments->chargeDue($subscription);

            if ($result->success) {
                $charged++;

                return;
            }

            $declined++;
            Log::warning('Recurring charge declined', [
                'subscription_id' => $subscription->id,
                'status' => $result->status,
                'reason' => $result->declineReason,
            ]);
        });

        $this->info("Charged {$charged} subscription(s); {$declined} declined.");

        return self::SUCCESS;
    }

    /**
     * Subscriptions with a stored credential, not pending cancellation, whose
     * current period has ended (or whose trial has just elapsed).
     *
     * @return \Illuminate\Support\LazyCollection<int, Subscription>
     */
    private function dueSubscriptions(): \Illuminate\Support\LazyCollection
    {
        return Subscription::query()
            ->whereNotNull('verifone_reuse_token')
            ->whereNull('canceled_at')
            ->where(function ($query) {
                $query->where(function ($active) {
                    $active->whereIn('status', [
                        SubscriptionStatus::Active->value,
                        SubscriptionStatus::PastDue->value,
                    ])->where(function ($period) {
                        $period->whereNull('current_period_ends_at')
                            ->orWhere('current_period_ends_at', '<=', now());
                    });
                })->orWhere(function ($trial) {
                    $trial->where('status', SubscriptionStatus::Trialing->value)
                        ->whereNotNull('trial_ends_at')
                        ->where('trial_ends_at', '<=', now());
                });
            })
            ->with('plan')
            ->cursor();
    }
}
