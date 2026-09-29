<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A payment session is one payment request sent to the client: one
 * Hyperswitch payment and its link. A lead can have several over time,
 * but only one OUVERTE at once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 50)->unique();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('EUR');
            $table->string('status', 20)->default('OUVERTE')->index();
            $table->string('hyperswitch_payment_id', 191)->nullable()->unique();
            $table->text('payment_url')->nullable();
            $table->string('client_email');
            $table->timestamp('sent_at')->nullable();
            $table->json('provider_payload')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['lead_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_sessions');
    }
};
