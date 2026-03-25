<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add columns if they don't already exist (partial run safety)
        if (! Schema::hasColumn('employee_shift', 'date')) {
            Schema::table('employee_shift', function (Blueprint $table) {
                $table->date('date')->nullable()->after('employee_id');
            });
        }

        if (! Schema::hasColumn('employee_shift', 'published')) {
            Schema::table('employee_shift', function (Blueprint $table) {
                $table->boolean('published')->default(false)->after('date');
            });
        }

        // Backfill date from created_at for any rows that have a null date
        DB::table('employee_shift')
            ->whereNull('date')
            ->update(['date' => DB::raw('DATE(created_at)')]);

        Schema::table('employee_shift', function (Blueprint $table) {
            $table->date('date')->nullable(false)->change();

            // MySQL uses the unique index to back foreign keys; drop FKs first, then swap the unique index
            $table->dropForeign(['shift_id']);
            $table->dropForeign(['employee_id']);

            $table->dropUnique(['shift_id', 'employee_id']);
            $table->unique(['shift_id', 'employee_id', 'date']);

            $table->foreign('shift_id')->references('id')->on('shifts')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employee_shift', function (Blueprint $table) {
            $table->dropForeign(['shift_id']);
            $table->dropForeign(['employee_id']);

            $table->dropUnique(['shift_id', 'employee_id', 'date']);
            $table->unique(['shift_id', 'employee_id']);

            $table->foreign('shift_id')->references('id')->on('shifts')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();

            $table->dropColumn(['date', 'published']);
        });
    }
};
