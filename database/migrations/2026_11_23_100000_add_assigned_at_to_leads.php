<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When a lead was last dispatched (assigned or reassigned), so the leads
 * table can list the latest dispatched first. Existing leads take the date
 * of their last assignment in the history; a lead assigned without any
 * history row (old imports) falls back to its creation date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('assigned_at')->nullable()->after('assigned_to');
            $table->index('assigned_at');
        });

        DB::table('leads')
            ->whereNotNull('assigned_to')
            ->update(['assigned_at' => DB::raw('(SELECT MAX(h.created_at) FROM lead_assignment_histories h WHERE h.lead_id = leads.id)')]);

        DB::table('leads')
            ->whereNotNull('assigned_to')
            ->whereNull('assigned_at')
            ->update(['assigned_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['assigned_at']);
            $table->dropColumn('assigned_at');
        });
    }
};
