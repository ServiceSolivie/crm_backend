<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Abbreviations that were being created as their own lead source
     * (e.g. "fb", "ig") get merged into the canonical source they mean.
     */
    protected const ALIASES = [
        'fb' => 'Facebook',
        'ig' => 'Instagram',
    ];

    public function up(): void
    {
        foreach (self::ALIASES as $aliasCode => $canonicalName) {
            $alias = DB::table('lead_sources')->where('code', $aliasCode)->first();

            if (! $alias) {
                continue;
            }

            $canonicalCode = Str::slug($canonicalName);
            $canonical = DB::table('lead_sources')->where('code', $canonicalCode)->first();

            if (! $canonical) {
                DB::table('lead_sources')->where('id', $alias->id)->update([
                    'name' => $canonicalName,
                    'code' => $canonicalCode,
                ]);

                continue;
            }

            DB::table('leads')->where('lead_source_id', $alias->id)->update([
                'lead_source_id' => $canonical->id,
            ]);

            DB::table('lead_sources')->where('id', $alias->id)->delete();
        }
    }

    public function down(): void
    {
        // Data merge is not reversible.
    }
};
