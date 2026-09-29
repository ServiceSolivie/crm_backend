<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Number of forced syncs (Sogecommerce Order/Get) done after the client's
 * return: at most 2, at +30 s and +70 s (PaymentSyncSchedule).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_sessions', function (Blueprint $table) {
            $table->unsignedTinyInteger('forced_syncs')->default(0)->after('sync_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('payment_sessions', function (Blueprint $table) {
            $table->dropColumn('forced_syncs');
        });
    }
};
