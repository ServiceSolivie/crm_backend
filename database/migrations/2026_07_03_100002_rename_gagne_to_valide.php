<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $statuses = [
        'NOUVEAU', 'PAS_DE_REPONSE', 'OCCUPE', 'RAPPEL', 'INTERESSE',
        'DEVIS_EN_COURS', 'DEVIS_ENVOYE', 'EN_ATTENTE_CLIENT',
        'VALIDE', 'GAGNE', 'PERDU', 'PAS_INTERESSE', 'MAUVAIS_NUMERO', 'LEAD_INVALIDE',
    ];

    private array $finalStatuses = [
        'NOUVEAU', 'PAS_DE_REPONSE', 'OCCUPE', 'RAPPEL', 'INTERESSE',
        'DEVIS_EN_COURS', 'DEVIS_ENVOYE', 'EN_ATTENTE_CLIENT',
        'VALIDE', 'PERDU', 'PAS_INTERESSE', 'MAUVAIS_NUMERO', 'LEAD_INVALIDE',
    ];

    public function up(): void
    {
        $expandEnum = implode("','", $this->statuses);

        DB::statement("ALTER TABLE leads MODIFY COLUMN status ENUM('{$expandEnum}') NOT NULL DEFAULT 'NOUVEAU'");
        DB::statement("ALTER TABLE lead_status_histories MODIFY COLUMN from_status ENUM('{$expandEnum}') NULL");
        DB::statement("ALTER TABLE lead_status_histories MODIFY COLUMN to_status ENUM('{$expandEnum}') NOT NULL");

        DB::table('leads')->where('status', 'GAGNE')->update(['status' => 'VALIDE']);
        DB::table('lead_status_histories')->where('from_status', 'GAGNE')->update(['from_status' => 'VALIDE']);
        DB::table('lead_status_histories')->where('to_status', 'GAGNE')->update(['to_status' => 'VALIDE']);

        $finalEnum = implode("','", $this->finalStatuses);
        DB::statement("ALTER TABLE leads MODIFY COLUMN status ENUM('{$finalEnum}') NOT NULL DEFAULT 'NOUVEAU'");
        DB::statement("ALTER TABLE lead_status_histories MODIFY COLUMN from_status ENUM('{$finalEnum}') NULL");
        DB::statement("ALTER TABLE lead_status_histories MODIFY COLUMN to_status ENUM('{$finalEnum}') NOT NULL");
    }

    public function down(): void
    {
        $expandEnum = implode("','", $this->statuses);

        DB::statement("ALTER TABLE leads MODIFY COLUMN status ENUM('{$expandEnum}') NOT NULL DEFAULT 'NOUVEAU'");
        DB::statement("ALTER TABLE lead_status_histories MODIFY COLUMN from_status ENUM('{$expandEnum}') NULL");
        DB::statement("ALTER TABLE lead_status_histories MODIFY COLUMN to_status ENUM('{$expandEnum}') NOT NULL");

        DB::table('leads')->where('status', 'VALIDE')->update(['status' => 'GAGNE']);
        DB::table('lead_status_histories')->where('from_status', 'VALIDE')->update(['from_status' => 'GAGNE']);
        DB::table('lead_status_histories')->where('to_status', 'VALIDE')->update(['to_status' => 'GAGNE']);

        $finalEnum = implode("','", array_diff($this->statuses, ['VALIDE']));
        DB::statement("ALTER TABLE leads MODIFY COLUMN status ENUM('{$finalEnum}') NOT NULL DEFAULT 'NOUVEAU'");
        DB::statement("ALTER TABLE lead_status_histories MODIFY COLUMN from_status ENUM('{$finalEnum}') NULL");
        DB::statement("ALTER TABLE lead_status_histories MODIFY COLUMN to_status ENUM('{$finalEnum}') NOT NULL");
    }
};
