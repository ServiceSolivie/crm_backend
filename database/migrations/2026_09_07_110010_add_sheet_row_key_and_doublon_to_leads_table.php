<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('sheet_row_key')->nullable()->after('lead_import_id')->index();
            $table->boolean('is_doublon')->default(false)->after('sheet_row_key');
            $table->foreignId('doublon_of_lead_id')->nullable()->after('is_doublon')->constrained('leads')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('doublon_of_lead_id');
            $table->dropColumn(['sheet_row_key', 'is_doublon']);
        });
    }
};
