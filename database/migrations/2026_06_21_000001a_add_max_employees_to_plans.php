<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            // null = unlimited; each plan allows up to this many active employees.
            $table->unsignedInteger('max_employees')->nullable()->after('price_yearly');
        });

        $caps = ['nettur' => 15, 'thettur' => 40, 'allur-pakkinn' => 100];

        foreach ($caps as $key => $max) {
            DB::table('plans')->where('key', $key)->update(['max_employees' => $max]);
        }
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('max_employees');
        });
    }
};
