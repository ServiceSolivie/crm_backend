<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integration doc (Hyperswitch + Sogecommerce) §5: keep the connector ids
 * and the bank error code/message on the payment request, and the bank code
 * on the payment attempt (e.g. 51 insufficient funds, 39 3DS refused).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_sessions', function (Blueprint $table) {
            $table->string('merchant_connector_id', 64)->nullable()->after('hyperswitch_payment_id');
            $table->string('connector_transaction_id', 100)->nullable()->after('merchant_connector_id');
            $table->string('error_code', 50)->nullable()->after('provider_status');
            $table->string('error_message')->nullable()->after('error_code');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('failure_code', 50)->nullable()->after('failure_reason');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('failure_code');
        });

        Schema::table('payment_sessions', function (Blueprint $table) {
            $table->dropColumn(['merchant_connector_id', 'connector_transaction_id', 'error_code', 'error_message']);
        });
    }
};
