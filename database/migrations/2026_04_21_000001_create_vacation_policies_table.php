<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacation_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('default_days_per_year')->default(24);
            $table->unsignedTinyInteger('vacation_year_start_month')->default(5);
            $table->unsignedTinyInteger('vacation_year_start_day')->default(1);
            $table->boolean('allow_carry_over')->default(false);
            $table->unsignedSmallInteger('max_carry_over_days')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacation_policies');
    }
};
