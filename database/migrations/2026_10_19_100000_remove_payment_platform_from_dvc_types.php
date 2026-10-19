<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const FIELD = 'payment_platform';

    private const NOTE = 'Suppression du champ « Plateforme de paiement »';

    /**
     * The "Plateforme de paiement" field leaves the DVC. Versions are never
     * edited: each DVC type whose current version still has the field gets a
     * new version without it. Older versions, and the contracts generated
     * with them, stay as they were.
     *
     * A database seeded after this change never had the field
     * (database/data/dvc/*.json): nothing happens there.
     */
    public function up(): void
    {
        foreach (DB::table('dvc_types')->whereNotNull('current_version_id')->get() as $type) {
            DB::transaction(function () use ($type) {
                $current = DB::table('dvc_type_versions')->where('id', $type->current_version_id)->lockForUpdate()->first();
                $schema = $current ? json_decode($current->schema, true) : null;

                if (! is_array($schema) || ! $this->hasField($schema)) {
                    return;
                }

                foreach ($schema['sections'] as $i => $section) {
                    if (($section['type'] ?? null) === 'fields' && isset($section['fields'])) {
                        $schema['sections'][$i]['fields'] = array_values(array_filter(
                            $section['fields'],
                            fn (array $field) => ($field['key'] ?? null) !== self::FIELD,
                        ));
                    }
                }

                $versionId = DB::table('dvc_type_versions')->insertGetId([
                    'dvc_type_id' => $type->id,
                    'version' => (int) DB::table('dvc_type_versions')->where('dvc_type_id', $type->id)->max('version') + 1,
                    'schema' => json_encode($schema, JSON_UNESCAPED_UNICODE),
                    'change_note' => self::NOTE,
                    'created_at' => now(),
                ]);

                DB::table('dvc_types')->where('id', $type->id)->update(['current_version_id' => $versionId, 'updated_at' => now()]);
            });
        }
    }

    /**
     * Back to the version before, for the types where this migration
     * published one and nothing was published after it.
     */
    public function down(): void
    {
        foreach (DB::table('dvc_types')->whereNotNull('current_version_id')->get() as $type) {
            $current = DB::table('dvc_type_versions')->where('id', $type->current_version_id)->first();

            if (! $current || $current->change_note !== self::NOTE || DB::table('contracts')->where('dvc_type_version_id', $current->id)->exists()) {
                continue;
            }

            $previous = DB::table('dvc_type_versions')
                ->where('dvc_type_id', $type->id)
                ->where('version', '<', $current->version)
                ->orderByDesc('version')
                ->first();

            if ($previous) {
                DB::table('dvc_types')->where('id', $type->id)->update(['current_version_id' => $previous->id, 'updated_at' => now()]);
                DB::table('dvc_type_versions')->where('id', $current->id)->delete();
            }
        }
    }

    private function hasField(array $schema): bool
    {
        foreach ($schema['sections'] ?? [] as $section) {
            foreach ($section['fields'] ?? [] as $field) {
                if (($field['key'] ?? null) === self::FIELD) {
                    return true;
                }
            }
        }

        return false;
    }
};
