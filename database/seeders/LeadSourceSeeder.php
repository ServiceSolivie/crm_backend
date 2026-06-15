<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LeadSourceSeeder extends Seeder
{
    /**
     * Seed the initial lead source lookup values.
     */
    public function run(): void
    {
        $sources = [
            ['name' => 'Facebook Ads', 'code' => 'facebook_ads'],
            ['name' => 'Google Ads', 'code' => 'google_ads'],
            ['name' => 'Website Form', 'code' => 'website_form'],
            ['name' => 'Referral', 'code' => 'referral'],
            ['name' => 'Cold Call List', 'code' => 'cold_call_list'],
            ['name' => 'Walk-in', 'code' => 'walk_in'],
            ['name' => 'Partner Agency', 'code' => 'partner_agency'],
            ['name' => 'Other', 'code' => 'other'],
        ];

        foreach ($sources as $source) {
            DB::table('lead_sources')->updateOrInsert(
                ['code' => $source['code']],
                [
                    'name' => $source['name'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
