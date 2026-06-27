<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Editable company details surfaced on the settings → company page.
            $table->string('kennitala')->nullable()->after('name');
            $table->string('phone')->nullable()->after('kennitala');
            $table->string('address')->nullable()->after('phone');
            $table->string('email')->nullable()->after('address');
            $table->string('locale')->default('is')->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'kennitala',
                'phone',
                'address',
                'email',
                'locale',
            ]);
        });
    }
};
