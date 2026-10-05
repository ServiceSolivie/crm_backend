<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refunds of paid payment sessions, through Hyperswitch. One row per
 * attempt: a failed refund stays in the history and a retry gets a new
 * row (and a new Hyperswitch refund_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_session_id')->constrained('payment_sessions')->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            // Chosen by the CRM before the call ("ref_" + 26 chars)
            $table->string('hyperswitch_refund_id', 30)->unique();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('EUR');
            $table->string('status', 20)->default('A_VERIFIER')->index();
            $table->text('reason');
            // Sogecommerce UUID of the cancellation or of the credit
            $table->string('connector_refund_id', 100)->nullable();
            $table->string('provider_status', 40)->nullable();
            $table->string('error_code', 50)->nullable();
            $table->string('error_message')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('next_sync_at')->nullable()->index();
            $table->unsignedSmallInteger('sync_attempts')->default(0);
            $table->timestamp('refunded_at')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['payment_session_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');
    }
};
