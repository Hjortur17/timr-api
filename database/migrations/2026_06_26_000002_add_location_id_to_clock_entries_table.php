<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clock_entries', function (Blueprint $table) {
            // The workplace whose geo-fence this clock-in was validated against.
            $table->foreignId('location_id')->nullable()->after('employee_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clock_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_id');
        });
    }
};
