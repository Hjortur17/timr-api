<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vacation_policies', function (Blueprint $table) {
            // Nullable (no DB default) — JSON column defaults are unsupported on some MySQL versions.
            // Defaults are applied in code: VacationService::policyFor() seeds [1..5] and all readers fall back.
            $table->json('working_days')->nullable()->after('vacation_year_start_day');
            $table->json('opening_hours')->nullable()->after('working_days');
        });
    }

    public function down(): void
    {
        Schema::table('vacation_policies', function (Blueprint $table) {
            $table->dropColumn(['working_days', 'opening_hours']);
        });
    }
};
