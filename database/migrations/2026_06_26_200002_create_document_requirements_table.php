<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_requirements', function (Blueprint $table) {
            $table->id();
            $table->string('insurance_type', 50);
            $table->string('client_type', 50)->nullable();
            $table->foreignId('document_type_id')->constrained('document_types')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['insurance_type', 'client_type', 'document_type_id'], 'doc_req_unique');
            $table->index(['insurance_type', 'client_type']);
        });

        $this->seedRequirements();
    }

    public function down(): void
    {
        Schema::dropIfExists('document_requirements');
    }

    private function seedRequirements(): void
    {
        $types = DB::table('document_types')->pluck('id', 'name');
        $now = now();

        $rows = [];

        $vehicleBase = ['CARTE_IDENTITE', 'PERMIS_CONDUIRE', 'CARTE_GRISE', 'RIB', 'RELEVE_INFORMATION'];
        $vehiclePro = [...$vehicleBase, 'EXTRAIT_KBIS'];

        foreach (['AUTO', 'MOTO'] as $insurance) {
            foreach ($vehicleBase as $doc) {
                $rows[] = ['insurance_type' => $insurance, 'client_type' => 'INDIVIDUAL', 'document_type_id' => $types[$doc], 'created_at' => $now, 'updated_at' => $now];
            }
            foreach ($vehiclePro as $doc) {
                $rows[] = ['insurance_type' => $insurance, 'client_type' => 'PROFESSIONAL', 'document_type_id' => $types[$doc], 'created_at' => $now, 'updated_at' => $now];
            }
        }

        foreach (['EXTRAIT_KBIS', 'CARTE_IDENTITE', 'NUMERO_SIRET'] as $doc) {
            $rows[] = ['insurance_type' => 'RC_PRO', 'client_type' => null, 'document_type_id' => $types[$doc], 'created_at' => $now, 'updated_at' => $now];
        }

        foreach (['EXTRAIT_KBIS', 'CONTRAT_CHANTIER', 'CARTE_IDENTITE', 'RIB'] as $doc) {
            $rows[] = ['insurance_type' => 'DECENNALE', 'client_type' => null, 'document_type_id' => $types[$doc], 'created_at' => $now, 'updated_at' => $now];
        }

        DB::table('document_requirements')->insert($rows);
    }
};
