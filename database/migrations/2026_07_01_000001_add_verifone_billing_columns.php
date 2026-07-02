<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reference-only model: we store opaque Verifone reference IDs that let us
        // run merchant-initiated recurring charges. No card data and no reusable
        // token are ever stored here.
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('verifone_checkout_id')->nullable()->after('verifone_reference');
            $table->string('verifone_stored_credential_ref')->nullable()->after('verifone_checkout_id');
            $table->string('verifone_scheme_reference')->nullable()->after('verifone_stored_credential_ref');
            $table->string('verifone_payment_contract_id')->nullable()->after('verifone_scheme_reference');
            $table->unsignedInteger('payment_sequence')->default(0)->after('verifone_payment_contract_id');
            $table->timestamp('last_charge_at')->nullable()->after('payment_sequence');
        });

        // One invoice per billing period — a hard backstop against a double charge
        // if the scheduler ever runs twice for the same period.
        Schema::table('invoices', function (Blueprint $table) {
            $table->unique(['company_id', 'period_start', 'period_end'], 'invoices_company_period_unique');
        });

        // Inbound webhook audit + replay-dedup store. eventId is globally unique so a
        // redelivered event is a no-op.
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('verifone');
            $table->string('event_id')->unique();
            $table->string('event_type')->nullable();
            $table->timestamp('event_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_company_period_unique');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'verifone_checkout_id',
                'verifone_stored_credential_ref',
                'verifone_scheme_reference',
                'verifone_payment_contract_id',
                'payment_sequence',
                'last_charge_at',
            ]);
        });
    }
};
