<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Verifone's hosted CARD_CAPTURE returns a reuse token (not a bare reference).
        // It is not card data — an opaque token bound to our token scope + merchant entity —
        // and is stored encrypted at rest via the model's `encrypted` cast.
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->text('verifone_reuse_token')->nullable()->after('verifone_checkout_id');
            $table->string('verifone_token_scope')->nullable()->after('verifone_reuse_token');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['verifone_reuse_token', 'verifone_token_scope']);
        });
    }
};
