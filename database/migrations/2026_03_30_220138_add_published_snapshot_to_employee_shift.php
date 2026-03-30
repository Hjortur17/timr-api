<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_shift', function (Blueprint $table) {
            $table->date('published_date')->nullable()->after('published');
            $table->unsignedBigInteger('published_employee_id')->nullable()->after('published_date');

            $table->foreign('published_employee_id')
                ->references('id')
                ->on('employees')
                ->nullOnDelete();
        });

        DB::table('employee_shift')
            ->where('published', true)
            ->update([
                'published_date' => DB::raw('date'),
                'published_employee_id' => DB::raw('employee_id'),
            ]);
    }

    public function down(): void
    {
        Schema::table('employee_shift', function (Blueprint $table) {
            $table->dropForeign(['published_employee_id']);
            $table->dropColumn(['published_date', 'published_employee_id']);
        });
    }
};
