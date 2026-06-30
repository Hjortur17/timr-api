<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Drop the free tier: every company now subscribes to a paid plan with a
     * 30-day trial before the first payment. Existing free-plan companies are
     * moved to Nettur (the new default), preserving their trial/status.
     */
    public function up(): void
    {
        $free = DB::table('plans')->where('key', 'free')->first();

        if ($free === null) {
            return;
        }

        $nettur = DB::table('plans')->where('key', 'nettur')->first();

        // Ensure a target plan exists before reassigning (covers fresh DBs that
        // somehow have free but not nettur).
        if ($nettur === null) {
            $netturId = DB::table('plans')->insertGetId([
                'key' => 'nettur',
                'name' => 'Nettur',
                'price_monthly' => 2490,
                'price_yearly' => 2075,
                'max_employees' => 15,
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $netturId = $nettur->id;
        }

        DB::table('subscriptions')->where('plan_id', $free->id)->update(['plan_id' => $netturId]);

        DB::table('plans')->where('id', $free->id)->delete();
    }

    /**
     * Irreversible simplification — the free tier is not restored on rollback.
     */
    public function down(): void
    {
        // no-op
    }
};
