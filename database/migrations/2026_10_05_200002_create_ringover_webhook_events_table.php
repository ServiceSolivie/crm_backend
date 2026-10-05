<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ringover_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event', 40)->index();
            $table->string('ringover_call_id', 64)->nullable()->index();
            // Ringover retries deliveries; identical events share this key and are stored once.
            $table->char('dedupe_key', 40)->unique();
            $table->json('payload');
            $table->unsignedSmallInteger('deliveries')->default(1);
            $table->timestamp('processed_at')->nullable()->index();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ringover_webhook_events');
    }
};
