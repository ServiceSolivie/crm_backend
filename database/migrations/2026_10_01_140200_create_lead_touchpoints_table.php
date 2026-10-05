<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_touchpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('lead_source_id')->constrained('lead_sources')->restrictOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->string('external_id');
            $table->json('payload');
            $table->boolean('is_test')->default(false);
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('received_at');
            $table->string('gcl_id')->nullable();
            $table->string('adgroup_id', 64)->nullable();
            $table->string('creative_id', 64)->nullable();
            $table->timestamps();

            $table->unique(['lead_source_id', 'external_id'], 'lead_touchpoints_source_external_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_touchpoints');
    }
};
