<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expiry of the Hyperswitch link ("expires_on", 15 min after creation): one
 * forced check just after it closes the link (PaymentSyncSchedule).
 *
 * Data:
 *  - backfill expires_at from the saved Hyperswitch answer; pending links
 *    already expired get their expiry check now (the job closes them);
 *  - remove sdk_authorization (it contains the client_secret) from every
 *    saved Hyperswitch answer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_sessions', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('paid_at');
        });

        foreach (['payment_sessions', 'payments'] as $table) {
            DB::table($table)->whereNotNull('provider_payload')->orderBy('id')->chunkById(200, function ($rows) use ($table) {
                foreach ($rows as $row) {
                    $payload = json_decode($row->provider_payload, true);
                    if (! is_array($payload)) {
                        continue;
                    }

                    $changes = [];

                    if ($table === 'payment_sessions' && ! empty($payload['expires_on'])) {
                        $expiresAt = Carbon::parse($payload['expires_on']);
                        $changes['expires_at'] = $expiresAt;

                        if (in_array($row->status, ['OUVERTE', 'A_VERIFIER'], true) && $row->next_sync_at === null && $expiresAt->isPast()) {
                            $changes['next_sync_at'] = now();
                            $changes['force_pending'] = true;
                        }
                    }

                    if (array_key_exists('sdk_authorization', $payload) || array_key_exists('client_secret', $payload)) {
                        unset($payload['sdk_authorization'], $payload['client_secret']);
                        $changes['provider_payload'] = json_encode($payload);
                    }

                    if ($changes) {
                        DB::table($table)->where('id', $row->id)->update($changes);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('payment_sessions', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });
    }
};
