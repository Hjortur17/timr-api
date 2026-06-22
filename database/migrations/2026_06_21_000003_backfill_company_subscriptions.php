<?php

use App\Services\SubscriptionService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Backfill a Free-plan trial for every company created before the
     * subscription system existed. Idempotent — safe to run on an empty DB
     * (no companies → no-op beyond ensuring the Free plan exists).
     */
    public function up(): void
    {
        app(SubscriptionService::class)->backfillMissing();
    }

    public function down(): void
    {
        // No-op: we don't delete subscriptions on rollback.
    }
};
