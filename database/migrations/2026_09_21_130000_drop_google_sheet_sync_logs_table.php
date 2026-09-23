<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The 15-minute Google Sheets polling (google:sync-leads) was removed:
 * leads arrive through the webhook, so nothing writes to or reads this log
 * table anymore. down() only recreates the empty table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('google_sheet_sync_logs');
    }

    public function down(): void
    {
        Schema::create('google_sheet_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('sheet_name');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('imported')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->unsignedInteger('last_row_synced')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('error_details')->nullable();
            $table->timestamps();
        });
    }
};
