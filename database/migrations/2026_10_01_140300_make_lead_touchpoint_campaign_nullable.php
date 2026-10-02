<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_touchpoints', function (Blueprint $table) {
            $table->foreignId('campaign_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('lead_touchpoints', function (Blueprint $table) {
            $table->foreignId('campaign_id')->nullable(false)->change();
        });
    }
};
