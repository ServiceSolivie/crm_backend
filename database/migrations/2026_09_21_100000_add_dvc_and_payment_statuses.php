<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DVC & payment statuses:
 *  - leads.dvc_status / dvc_signed_at (computed from contracts + the "DVC" document)
 *  - leads.payment_status becomes a plain string (new values EN_ATTENTE, REMBOURSE)
 *  - payments get a status and a source, plus fields for the future Hyperswitch webhook
 *  - a "DVC" document type for the signed copy
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('dvc_status', 30)->default('A_GENERER')->after('payment_status')->index();
            $table->timestamp('dvc_signed_at')->nullable()->after('dvc_status');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->string('payment_status', 30)->nullable()->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('status', 20)->default('REUSSI')->after('amount')->index();
            $table->string('source', 20)->default('MANUEL')->after('status');
            $table->string('external_id', 191)->nullable()->unique()->after('source');
            $table->string('failure_reason')->nullable()->after('notes');
            $table->json('provider_payload')->nullable()->after('failure_reason');
            $table->timestamp('status_changed_at')->nullable()->after('provider_payload');
            $table->foreignId('status_changed_by')->nullable()->after('status_changed_at')->constrained('users')->nullOnDelete();
        });

        if (! DB::table('document_types')->where('name', 'DVC')->exists()) {
            DB::table('document_types')->insert([
                'name' => 'DVC',
                'label' => 'DVC signé',
                'is_active' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Backfill: a generated DVC means we are waiting for the signature…
        DB::table('leads')
            ->whereIn('id', DB::table('contracts')->whereNotNull('lead_id')->select('lead_id'))
            ->update(['dvc_status' => 'EN_ATTENTE_SIGNATURE']);

        // …and an uploaded signed copy means it is signed
        DB::table('leads')
            ->join('lead_documents', 'lead_documents.lead_id', '=', 'leads.id')
            ->where('lead_documents.document_type', 'DVC')
            ->update(['leads.dvc_status' => 'SIGNE', 'leads.dvc_signed_at' => DB::raw('lead_documents.created_at')]);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('status_changed_by');
            $table->dropUnique(['external_id']);
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'source', 'external_id', 'failure_reason', 'provider_payload', 'status_changed_at']);
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['dvc_status']);
            $table->dropColumn(['dvc_status', 'dvc_signed_at']);
        });

        DB::table('leads')->whereIn('payment_status', ['EN_ATTENTE', 'REMBOURSE'])->update(['payment_status' => 'NON_PAYE']);
        Schema::table('leads', function (Blueprint $table) {
            $table->enum('payment_status', ['NON_PAYE', 'PARTIELLEMENT_PAYE', 'PAYE'])->nullable()->change();
        });

        DB::table('document_types')->where('name', 'DVC')->delete();
    }
};
