<?php

use App\Support\PhoneNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('phone_e164', 20)->nullable()->after('phone')->index();
        });

        // Backfill existing leads (soft-deleted ones included, so calls can still match them).
        DB::table('leads')->select(['id', 'phone'])->orderBy('id')->chunkById(500, function ($leads) {
            foreach ($leads as $lead) {
                DB::table('leads')->where('id', $lead->id)->update([
                    'phone_e164' => PhoneNumber::toE164($lead->phone),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['phone_e164']);
            $table->dropColumn('phone_e164');
        });
    }
};
