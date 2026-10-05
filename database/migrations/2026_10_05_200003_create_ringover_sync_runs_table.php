<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ringover_sync_runs', function (Blueprint $table) {
            $table->id();
            // Period of calls fetched from Ringover. The next run starts from the
            // last successful window_to, so an outage of any length is caught up.
            $table->timestamp('window_from')->nullable();
            $table->timestamp('window_to')->nullable();
            $table->enum('status', ['running', 'success', 'failed'])->default('running')->index();
            $table->unsignedInteger('calls_synced')->default(0);
            $table->unsignedInteger('pending_webhooks_processed')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ringover_sync_runs');
    }
};
