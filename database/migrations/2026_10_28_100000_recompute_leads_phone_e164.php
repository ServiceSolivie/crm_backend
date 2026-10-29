<?php

use App\Support\PhoneNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Numbers stored with their country code but no "+" (596696777862) were
     * read as French and left without a normalised copy: compute it again.
     */
    public function up(): void
    {
        DB::table('leads')->select(['id', 'phone', 'phone_e164'])->orderBy('id')->chunkById(500, function ($leads) {
            foreach ($leads as $lead) {
                $e164 = PhoneNumber::toE164($lead->phone);

                if ($e164 !== $lead->phone_e164) {
                    DB::table('leads')->where('id', $lead->id)->update(['phone_e164' => $e164]);
                }
            }
        });
    }

    public function down(): void
    {
        // Nothing to undo: the copy is derived from the stored number.
    }
};
