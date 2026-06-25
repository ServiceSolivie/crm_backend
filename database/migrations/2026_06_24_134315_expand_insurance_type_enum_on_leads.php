<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE leads MODIFY COLUMN insurance_type ENUM(
            'AUTO','MOTO','RC_PRO','MUTUELLE_SANTE','EMPRUNTEUR',
            'CREDIT_CONSOMMATION','RACHAT_CREDIT','CREDIT_IMMOBILIER',
            'DECENNALE','TAXI_VTC','AUTRE'
        ) NULL DEFAULT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE leads MODIFY COLUMN insurance_type ENUM(
            'AUTO','MOTO','RC_PRO','MUTUELLE_SANTE','EMPRUNTEUR',
            'CREDIT_CONSOMMATION','RACHAT_CREDIT','CREDIT_IMMOBILIER'
        ) NULL DEFAULT NULL");
    }
};
