<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('label');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        DB::table('document_types')->insert([
            ['name' => 'CARTE_IDENTITE', 'label' => "Carte d'identité", 'is_active' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'PERMIS_CONDUIRE', 'label' => 'Permis de conduire', 'is_active' => true, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'CARTE_GRISE', 'label' => 'Carte grise', 'is_active' => true, 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'RIB', 'label' => 'RIB', 'is_active' => true, 'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'RELEVE_INFORMATION', 'label' => "Relevé d'information", 'is_active' => true, 'sort_order' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'EXTRAIT_KBIS', 'label' => 'Extrait Kbis', 'is_active' => true, 'sort_order' => 6, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'NUMERO_SIRET', 'label' => 'Numéro SIRET', 'is_active' => true, 'sort_order' => 7, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'CONTRAT_CHANTIER', 'label' => 'Contrat Chantier', 'is_active' => true, 'sort_order' => 8, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('document_types');
    }
};
