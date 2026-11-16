<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Insurance types become a list the super admin manages, and each type can
 * be placed on its own lead page (Décennale, Flotte & Camion…). Types left
 * without a page stay on the « Leads » page.
 */
return new class extends Migration
{
    /** The codes of the old enum: they stay on the leads that have them */
    private array $legacy = [
        'AUTO', 'MOTO', 'RC_PRO', 'MUTUELLE_SANTE', 'EMPRUNTEUR', 'CREDIT_CONSOMMATION',
        'RACHAT_CREDIT', 'CREDIT_IMMOBILIER', 'DECENNALE', 'TAXI_VTC', 'AUTRE',
    ];

    public function up(): void
    {
        Schema::create('lead_pages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('slug', 60)->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('insurance_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('label', 80);
            $table->string('short_label', 40);
            $table->foreignId('lead_page_id')->nullable()->constrained('lead_pages')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_company_info')->default(false);
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        $decennale = DB::table('lead_pages')->insertGetId(['name' => 'Décennale', 'slug' => 'decennale', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $fleet = DB::table('lead_pages')->insertGetId(['name' => 'Flotte & Camion', 'slug' => 'flotte-camion', 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now]);

        // code, label, short label, page, company info, system
        $types = [
            ['AUTO', 'Assurance Auto', 'Auto', null, false, true],
            ['MOTO', 'Assurance Moto', 'Moto', null, false, true],
            ['RC_PRO', 'Responsabilité Civile Pro', 'RC Pro', null, false, true],
            ['MUTUELLE_SANTE', 'Mutuelle Santé', 'Santé', null, false, true],
            ['EMPRUNTEUR', 'Assurance Emprunteur', 'Emprunteur', null, false, true],
            ['CREDIT_CONSOMMATION', 'Crédit Consommation', 'Crédit conso', null, false, true],
            ['RACHAT_CREDIT', 'Rachat de Crédit', 'Rachat crédit', null, false, true],
            ['CREDIT_IMMOBILIER', 'Crédit Immobilier', 'Crédit immo', null, false, true],
            ['DECENNALE', 'Décennale', 'Décennale', $decennale, true, true],
            ['TAXI_VTC', 'Taxi / VTC', 'Taxi/VTC', null, false, true],
            ['FLOTTE', 'Assurance Flotte', 'Flotte', $fleet, false, false],
            ['CAMION', 'Assurance Camion', 'Camion', $fleet, false, false],
            ['AUTRE', 'Autre', 'Autre', null, false, true],
        ];

        DB::table('insurance_types')->insert(array_map(fn (array $t, int $i) => [
            'code' => $t[0],
            'label' => $t[1],
            'short_label' => $t[2],
            'lead_page_id' => $t[3],
            'requires_company_info' => $t[4],
            'is_system' => $t[5],
            'is_active' => true,
            'sort_order' => $i + 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], $types, array_keys($types)));

        // The ENUM only accepted the old codes; as text it takes any managed type
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE leads MODIFY COLUMN insurance_type VARCHAR(40) NULL DEFAULT NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            if (DB::table('leads')->whereNotNull('insurance_type')->whereNotIn('insurance_type', $this->legacy)->exists()) {
                throw new RuntimeException('Leads use insurance types added after this migration (FLOTTE, CAMION…): move them to an old type before rolling back.');
            }

            $enumStr = implode("','", $this->legacy);
            DB::statement("ALTER TABLE leads MODIFY COLUMN insurance_type ENUM('{$enumStr}') NULL DEFAULT NULL");
        }

        Schema::dropIfExists('insurance_types');
        Schema::dropIfExists('lead_pages');
    }
};
