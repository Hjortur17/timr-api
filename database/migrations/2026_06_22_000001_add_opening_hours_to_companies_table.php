<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // General company opening time (decoupled from the vacation policy).
            // Shape: { days[7], time_mode, open, close, times[7], exc[] }. Nullable
            // because JSON column defaults are unsupported on some MySQL versions;
            // defaults are applied in OpeningHoursService.
            $table->json('opening_hours')->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('opening_hours');
        });
    }
};
