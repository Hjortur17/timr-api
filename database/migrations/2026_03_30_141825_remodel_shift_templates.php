<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_templates', function (Blueprint $table) {
            $table->foreignId('shift_id')->nullable()->after('company_id')->constrained()->cascadeOnDelete();
            $table->string('pattern')->after('description');
            $table->json('blocks')->after('pattern');
        });

        Schema::create('shift_template_employee', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['shift_template_id', 'sort_order']);
        });

        Schema::dropIfExists('shift_template_entries');
    }

    public function down(): void
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

        Schema::dropIfExists('shift_template_employee');

        Schema::table('shift_templates', function (Blueprint $table) {
            $table->dropForeign(['shift_id']);
            $table->dropColumn(['shift_id', 'pattern', 'blocks']);
        });
    }
};
