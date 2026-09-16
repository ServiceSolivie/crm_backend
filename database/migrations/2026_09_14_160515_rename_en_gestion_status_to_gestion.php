<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $currentStatuses = [
        'NOUVEAU', 'PAS_DE_REPONSE', 'OCCUPE', 'RAPPEL', 'INTERESSE',
        'DEVIS_EN_COURS', 'DEVIS_ENVOYE', 'EN_ATTENTE_CLIENT',
        'VALIDE', 'PERDU', 'PAS_INTERESSE', 'MAUVAIS_NUMERO', 'LEAD_INVALIDE',
        'EN_GESTION', 'A_CORRIGER', 'CALL2_OK', 'CALL2_KO', 'PDG_OK', 'PDG_KO',
    ];

    private array $newStatuses = [
        'NOUVEAU', 'PAS_DE_REPONSE', 'OCCUPE', 'RAPPEL', 'INTERESSE',
        'DEVIS_EN_COURS', 'DEVIS_ENVOYE', 'EN_ATTENTE_CLIENT',
        'VALIDE', 'PERDU', 'PAS_INTERESSE', 'MAUVAIS_NUMERO', 'LEAD_INVALIDE',
        'GESTION', 'A_CORRIGER', 'CALL2_OK', 'CALL2_KO', 'PDG_OK', 'PDG_KO',
    ];

    public function up(): void
    {
        $enumStr = implode("','", array_unique(array_merge($this->currentStatuses, $this->newStatuses)));

        DB::statement("ALTER TABLE leads MODIFY COLUMN status ENUM('{$enumStr}') NOT NULL DEFAULT 'NOUVEAU'");
        DB::statement("ALTER TABLE lead_status_histories MODIFY COLUMN from_status ENUM('{$enumStr}') NULL");
        DB::statement("ALTER TABLE lead_status_histories MODIFY COLUMN to_status ENUM('{$enumStr}') NOT NULL");

        DB::table('leads')->where('status', 'EN_GESTION')->update(['status' => 'GESTION']);
        DB::table('lead_status_histories')->where('from_status', 'EN_GESTION')->update(['from_status' => 'GESTION']);
        DB::table('lead_status_histories')->where('to_status', 'EN_GESTION')->update(['to_status' => 'GESTION']);

        $newEnumStr = implode("','", $this->newStatuses);
        DB::statement("ALTER TABLE leads MODIFY COLUMN status ENUM('{$newEnumStr}') NOT NULL DEFAULT 'NOUVEAU'");
        DB::statement("ALTER TABLE lead_status_histories MODIFY COLUMN from_status ENUM('{$newEnumStr}') NULL");
        DB::statement("ALTER TABLE lead_status_histories MODIFY COLUMN to_status ENUM('{$newEnumStr}') NOT NULL");
    }

    public function down(): void
    {
        $enumStr = implode("','", array_unique(array_merge($this->currentStatuses, $this->newStatuses)));

        DB::statement("ALTER TABLE leads MODIFY COLUMN status ENUM('{$enumStr}') NOT NULL DEFAULT 'NOUVEAU'");
        DB::statement("ALTER TABLE lead_status_histories MODIFY COLUMN from_status ENUM('{$enumStr}') NULL");
        DB::statement("ALTER TABLE lead_status_histories MODIFY COLUMN to_status ENUM('{$enumStr}') NOT NULL");

        DB::table('leads')->where('status', 'GESTION')->update(['status' => 'EN_GESTION']);
        DB::table('lead_status_histories')->where('from_status', 'GESTION')->update(['from_status' => 'EN_GESTION']);
        DB::table('lead_status_histories')->where('to_status', 'GESTION')->update(['to_status' => 'EN_GESTION']);

        $oldEnumStr = implode("','", $this->currentStatuses);
        DB::statement("ALTER TABLE leads MODIFY COLUMN status ENUM('{$oldEnumStr}') NOT NULL DEFAULT 'NOUVEAU'");
        DB::statement("ALTER TABLE lead_status_histories MODIFY COLUMN from_status ENUM('{$oldEnumStr}') NULL");
        DB::statement("ALTER TABLE lead_status_histories MODIFY COLUMN to_status ENUM('{$oldEnumStr}') NOT NULL");
    }
};
