<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'employee_id']);
            $table->dropForeign(['employee_id']);
            $table->dropColumn('employee_id');
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->foreignId('employee_id')->after('company_id')->constrained('users')->cascadeOnDelete();
            $table->index(['company_id', 'employee_id']);
        });
    }
};
