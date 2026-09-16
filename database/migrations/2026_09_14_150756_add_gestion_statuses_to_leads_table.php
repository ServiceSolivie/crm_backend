<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $oldStatuses = [
        'NOUVEAU', 'PAS_DE_REPONSE', 'OCCUPE', 'RAPPEL', 'INTERESSE',
        'DEVIS_EN_COURS', 'DEVIS_ENVOYE', 'EN_ATTENTE_CLIENT',
        'VALIDE', 'PERDU', 'PAS_INTERESSE', 'MAUVAIS_NUMERO', 'LEAD_INVALIDE',
    ];

    private array $newStatuses = [
        'EN_GESTION', 'A_CORRIGER', 'CALL2_OK', 'CALL2_KO', 'PDG_OK', 'PDG_KO',
    ];

    public function up(): void
    {
        $enumStr = implode("','", array_merge($this->oldStatuses, $this->newStatuses));

        DB::statement("ALTER TABLE leads MODIFY COLUMN status ENUM('{$enumStr}') NOT NULL DEFAULT 'NOUVEAU'");
        DB::statement("ALTER TABLE lead_status_histories MODIFY COLUMN from_status ENUM('{$enumStr}') NULL");
        DB::statement("ALTER TABLE lead_status_histories MODIFY COLUMN to_status ENUM('{$enumStr}') NOT NULL");
    }

    public function down(): void
    {
        $enumStr = implode("','", $this->oldStatuses);

        DB::statement("ALTER TABLE leads MODIFY COLUMN status ENUM('{$enumStr}') NOT NULL DEFAULT 'NOUVEAU'");
        DB::statement("ALTER TABLE lead_status_histories MODIFY COLUMN from_status ENUM('{$enumStr}') NULL");
        DB::statement("ALTER TABLE lead_status_histories MODIFY COLUMN to_status ENUM('{$enumStr}') NOT NULL");
    }
};
