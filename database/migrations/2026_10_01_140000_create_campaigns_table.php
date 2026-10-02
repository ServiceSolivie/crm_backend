<?php

use App\Enums\InsuranceTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_source_id')->constrained('lead_sources')->restrictOnDelete();
            $table->string('name');
            $table->string('external_campaign_id', 64);
            $table->string('form_id', 64);
            $table->string('form_name')->nullable();
            $table->enum('insurance_type', InsuranceTypeEnum::values());
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['lead_source_id', 'external_campaign_id', 'form_id'], 'campaigns_source_external_form_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
