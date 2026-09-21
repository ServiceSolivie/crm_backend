<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Appointment length; the calendar used to draw every appointment as 30 minutes
        Schema::table('appointments', function (Blueprint $table) {
            $table->unsignedSmallInteger('duration_minutes')->default(30)->after('scheduled_at');
        });

        // Structured details of a status change (e.g. what gestion flagged when sending a lead back)
        Schema::table('lead_status_histories', function (Blueprint $table) {
            $table->json('meta')->nullable()->after('comment');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('duration_minutes');
        });

        Schema::table('lead_status_histories', function (Blueprint $table) {
            $table->dropColumn('meta');
        });
    }
};
