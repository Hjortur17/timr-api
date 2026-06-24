<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            // 'global' follows the company general time; 'custom' uses opening_hours.
            $table->string('opening_hours_mode')->default('global')->after('geo_fence_radius');
            $table->json('opening_hours')->nullable()->after('opening_hours_mode');

            // GPS becomes optional so a workplace can have geofencing turned off.
            $table->decimal('latitude', 10, 7)->nullable()->change();
            $table->decimal('longitude', 10, 7)->nullable()->change();
            $table->unsignedInteger('geo_fence_radius')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn(['opening_hours_mode', 'opening_hours']);
            $table->decimal('latitude', 10, 7)->nullable(false)->change();
            $table->decimal('longitude', 10, 7)->nullable(false)->change();
            $table->unsignedInteger('geo_fence_radius')->nullable(false)->change();
        });
    }
};
