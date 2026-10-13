<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $systemStatuses = [
        'NOUVEAU', 'PAS_DE_REPONSE', 'OCCUPE', 'RAPPEL', 'INTERESSE',
        'DEVIS_EN_COURS', 'DEVIS_ENVOYE', 'EN_ATTENTE_CLIENT',
        'VALIDE', 'PERDU', 'PAS_INTERESSE', 'MAUVAIS_NUMERO', 'LEAD_INVALIDE',
        'GESTION', 'A_CORRIGER', 'CALL2_OK', 'CALL2_KO', 'PDG_OK', 'PDG_KO',
    ];

    /**
     * The status columns were ENUMs listing the system statuses, so the
     * database refused any other value. As text they also accept the codes
     * of the custom statuses (lead_statuses). Existing values and indexes
     * are kept as they are.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE leads MODIFY COLUMN status VARCHAR(40) NOT NULL DEFAULT 'NOUVEAU'");
        DB::statement('ALTER TABLE lead_status_histories MODIFY COLUMN from_status VARCHAR(40) NULL');
        DB::statement('ALTER TABLE lead_status_histories MODIFY COLUMN to_status VARCHAR(40) NOT NULL');
    }

    public function down(): void
    {
        $customInUse = DB::table('leads')->whereNotIn('status', $this->systemStatuses)->exists()
            || DB::table('lead_status_histories')->whereNotIn('to_status', $this->systemStatuses)->exists()
            || DB::table('lead_status_histories')->whereNotNull('from_status')->whereNotIn('from_status', $this->systemStatuses)->exists();

        if ($customInUse) {
            throw new RuntimeException('Custom lead statuses are in use on leads or in their history: move those leads to a system status before rolling back.');
        }

        $enumStr = implode("','", $this->systemStatuses);

        DB::statement("ALTER TABLE leads MODIFY COLUMN status ENUM('{$enumStr}') NOT NULL DEFAULT 'NOUVEAU'");
        DB::statement("ALTER TABLE lead_status_histories MODIFY COLUMN from_status ENUM('{$enumStr}') NULL");
        DB::statement("ALTER TABLE lead_status_histories MODIFY COLUMN to_status ENUM('{$enumStr}') NOT NULL");
    }
};
