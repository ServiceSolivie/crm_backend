<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('lead_assignment_histories', function (Blueprint $table) {
            // Holds the sheet's own claimed "old agent" name when a lead
            // comes in flagged as a doublon (resubmission) and that name
            // doesn't match any real user — from_user_id stays null in
            // that case (it's a strict FK), so this is the only way to
            // still show "previously with X" in the history timeline.
            $table->string('from_agent_name')->nullable()->after('from_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_assignment_histories', function (Blueprint $table) {
            $table->dropColumn('from_agent_name');
        });
    }
};
