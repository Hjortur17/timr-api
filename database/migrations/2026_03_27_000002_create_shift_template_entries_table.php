<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_template_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('day_offset');
            $table->timestamps();

            $table->index(['shift_template_id', 'day_offset']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_template_entries');
    }
};
