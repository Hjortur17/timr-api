<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The "unlimited" (null) tier was retired with the free plan; every plan now
        // has an explicit cap. Backfill any stragglers, then enforce non-null.
        DB::table('plans')->whereNull('max_employees')->update(['max_employees' => 15]);

        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('max_employees')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('max_employees')->nullable()->change();
        });
    }
};
