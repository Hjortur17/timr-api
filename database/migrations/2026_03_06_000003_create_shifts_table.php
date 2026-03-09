<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->time('start_time');
            $table->time('end_time');
            $table->text('notes')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();

            $table->index(['company_id', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
