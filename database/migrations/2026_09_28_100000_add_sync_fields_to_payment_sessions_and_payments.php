<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 (no webhook): the CRM pulls the payment status from Hyperswitch.
 *  - payment_sessions: hash of the result-page token, Hyperswitch status and
 *    the tracking of the syncs (normal / forced, next one with a backoff)
 *  - payments: the attempts are grouped under their payment session
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_sessions', function (Blueprint $table) {
            // SHA-256 of the result-page token (the token itself is never stored)
            $table->char('public_token_hash', 64)->nullable()->unique()->after('payment_url');
            $table->string('provider_status', 40)->nullable()->after('status');
            $table->timestamp('returned_at')->nullable()->after('sent_at');
            $table->timestamp('paid_at')->nullable()->after('returned_at');
            $table->timestamp('last_synced_at')->nullable()->after('paid_at');
            $table->timestamp('last_forced_sync_at')->nullable()->after('last_synced_at');
            $table->timestamp('next_sync_at')->nullable()->index()->after('last_forced_sync_at');
            $table->unsignedSmallInteger('sync_attempts')->default(0)->after('next_sync_at');
            $table->boolean('force_pending')->default(false)->after('sync_attempts');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('payment_session_id')->nullable()->after('lead_id')->constrained('payment_sessions')->nullOnDelete();
        });

        // Links already sent before this change: follow them from now on
        DB::table('payment_sessions')
            ->whereIn('status', ['OUVERTE', 'A_VERIFIER'])
            ->whereNotNull('hyperswitch_payment_id')
            ->update(['next_sync_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_session_id');
        });

        Schema::table('payment_sessions', function (Blueprint $table) {
            $table->dropUnique(['public_token_hash']);
            $table->dropIndex(['next_sync_at']);
            $table->dropColumn([
                'public_token_hash', 'provider_status', 'returned_at', 'paid_at', 'last_synced_at',
                'last_forced_sync_at', 'next_sync_at', 'sync_attempts', 'force_pending',
            ]);
        });
    }
};
