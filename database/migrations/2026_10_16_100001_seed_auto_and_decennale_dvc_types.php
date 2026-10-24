<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The two DVCs that were hard-coded (AutoDvcTemplate, DecennaleDvcTemplate
 * and their Blade views) become DVC types, version 1. Their schemas live in
 * database/data/dvc/*.json; from now on the super admin edits them in the CRM.
 *
 * Contracts generated before keep their stored PDF and stay without a
 * version: nothing is re-rendered.
 */
return new class extends Migration
{
    private array $types = [
        'AUTO' => ['label' => 'Devoir de Conseil — Assurance Auto', 'filename_prefix' => 'DVC', 'file' => 'auto.json'],
        'DECENNALE' => ['label' => 'Devoir de Conseil — Assurance Décennale', 'filename_prefix' => 'DVC-DECENNALE', 'file' => 'decennale.json'],
    ];

    public function up(): void
    {
        foreach ($this->types as $insuranceType => $type) {
            if (DB::table('dvc_types')->where('insurance_type', $insuranceType)->exists()) {
                continue;
            }

            $schema = file_get_contents(database_path("data/dvc/{$type['file']}"));

            DB::transaction(function () use ($insuranceType, $type, $schema) {
                $typeId = DB::table('dvc_types')->insertGetId([
                    'insurance_type' => $insuranceType,
                    'label' => $type['label'],
                    'filename_prefix' => $type['filename_prefix'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $versionId = DB::table('dvc_type_versions')->insertGetId([
                    'dvc_type_id' => $typeId,
                    'version' => 1,
                    'schema' => json_encode(json_decode($schema, true), JSON_UNESCAPED_UNICODE),
                    'change_note' => 'Reprise du modèle existant',
                    'created_at' => now(),
                ]);

                DB::table('dvc_types')->where('id', $typeId)->update(['current_version_id' => $versionId]);
            });
        }
    }

    public function down(): void
    {
        DB::table('dvc_types')->whereIn('insurance_type', array_keys($this->types))->update(['current_version_id' => null]);
        DB::table('dvc_types')->whereIn('insurance_type', array_keys($this->types))->delete();
    }
};
