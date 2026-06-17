<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE appointments MODIFY COLUMN status ENUM('PLANIFIE','CONFIRME','REALISE','ANNULE','REPORTE','NON_VENU') NOT NULL DEFAULT 'PLANIFIE'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE appointments MODIFY COLUMN status ENUM('PLANIFIE','REALISE','ANNULE','REPORTE') NOT NULL DEFAULT 'PLANIFIE'");
    }
};
