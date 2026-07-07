<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $newStatuses = [
        'NOUVEAU', 'PAS_DE_REPONSE', 'OCCUPE', 'RAPPEL', 'INTERESSE',
        'DEVIS_EN_COURS', 'DEVIS_ENVOYE', 'EN_ATTENTE_CLIENT',
        'VALIDE', 'PERDU', 'PAS_INTERESSE', 'MAUVAIS_NUMERO', 'LEAD_INVALIDE',
    ];

    private array $oldStatuses = [
        'NRP', 'VALIDE', 'RAPPEL', 'RENDEZ_VOUS_ASSURE', 'PAS_INTERESSEE',
    ];

    public function up(): void
    {
        $enumStr = implode("','", array_unique(array_merge($this->oldStatuses, $this->newStatuses)));

        // Step 1: Expand enum columns to accept both old and new values
        DB::statement("ALTER TABLE leads MODIFY COLUMN status ENUM('{$enumStr}') NOT NULL DEFAULT 'NOUVEAU'");
        DB::statement("ALTER TABLE lead_status_histories MODIFY COLUMN from_status ENUM('{$enumStr}') NULL");
        DB::statement("ALTER TABLE lead_status_histories MODIFY COLUMN to_status ENUM('{$enumStr}') NOT NULL");

        // Step 2: Migrate old values to new
        DB::table('leads')->where('status', 'NRP')->update(['status' => 'PAS_DE_REPONSE']);
        DB::table('leads')->where('status', 'VALIDE')->update(['status' => 'VALIDE']);
        DB::table('leads')->where('status', 'RENDEZ_VOUS_ASSURE')->update(['status' => 'RAPPEL']);
        DB::table('leads')->where('status', 'PAS_INTERESSEE')->update(['status' => 'PAS_INTERESSE']);

        DB::table('lead_status_histories')->where('from_status', 'NRP')->update(['from_status' => 'PAS_DE_REPONSE']);
        DB::table('lead_status_histories')->where('from_status', 'VALIDE')->update(['from_status' => 'VALIDE']);
        DB::table('lead_status_histories')->where('from_status', 'RENDEZ_VOUS_ASSURE')->update(['from_status' => 'RAPPEL']);
        DB::table('lead_status_histories')->where('from_status', 'PAS_INTERESSEE')->update(['from_status' => 'PAS_INTERESSE']);

        DB::table('lead_status_histories')->where('to_status', 'NRP')->update(['to_status' => 'PAS_DE_REPONSE']);
        DB::table('lead_status_histories')->where('to_status', 'VALIDE')->update(['to_status' => 'VALIDE']);
        DB::table('lead_status_histories')->where('to_status', 'RENDEZ_VOUS_ASSURE')->update(['to_status' => 'RAPPEL']);
        DB::table('lead_status_histories')->where('to_status', 'PAS_INTERESSEE')->update(['to_status' => 'PAS_INTERESSE']);

        // Step 3: Shrink enum to only new values
        $newEnumStr = implode("','", $this->newStatuses);
        DB::statement("ALTER TABLE leads MODIFY COLUMN status ENUM('{$newEnumStr}') NOT NULL DEFAULT 'NOUVEAU'");
        DB::statement("ALTER TABLE lead_status_histories MODIFY COLUMN from_status ENUM('{$newEnumStr}') NULL");
        DB::statement("ALTER TABLE lead_status_histories MODIFY COLUMN to_status ENUM('{$newEnumStr}') NOT NULL");
    }

    public function down(): void
    {
        $allStr = implode("','", array_unique(array_merge($this->oldStatuses, $this->newStatuses)));

        DB::statement("ALTER TABLE leads MODIFY COLUMN status ENUM('{$allStr}') NOT NULL DEFAULT 'NRP'");
        DB::statement("ALTER TABLE lead_status_histories MODIFY COLUMN from_status ENUM('{$allStr}') NULL");
        DB::statement("ALTER TABLE lead_status_histories MODIFY COLUMN to_status ENUM('{$allStr}') NOT NULL");

        DB::table('leads')->where('status', 'PAS_DE_REPONSE')->update(['status' => 'NRP']);
        DB::table('leads')->where('status', 'VALIDE')->update(['status' => 'VALIDE']);
        DB::table('leads')->where('status', 'PAS_INTERESSE')->update(['status' => 'PAS_INTERESSEE']);

        $oldEnumStr = implode("','", $this->oldStatuses);
        DB::statement("ALTER TABLE leads MODIFY COLUMN status ENUM('{$oldEnumStr}') NOT NULL DEFAULT 'NRP'");
        DB::statement("ALTER TABLE lead_status_histories MODIFY COLUMN from_status ENUM('{$oldEnumStr}') NULL");
        DB::statement("ALTER TABLE lead_status_histories MODIFY COLUMN to_status ENUM('{$oldEnumStr}') NOT NULL");
    }
};
