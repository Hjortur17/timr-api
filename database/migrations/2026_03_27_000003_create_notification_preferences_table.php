<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['employee_id', 'type']);
        });

        Schema::table('employee_shift', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable()->after('published');
        });
    }

    public function down(): void
    {
        Schema::table('employee_shift', function (Blueprint $table) {
            $table->dropColumn('reminder_sent_at');
        });

        Schema::dropIfExists('notification_preferences');
    }
};
