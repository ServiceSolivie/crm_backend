<?php

use App\Enums\InsuranceTypeEnum;
use App\Enums\LeadStatusEnum;
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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('city')->nullable();
            $table->date('birth_date')->nullable();
            $table->foreignId('lead_source_id')->nullable()->constrained('lead_sources')->nullOnDelete();
            $table->enum('insurance_type', InsuranceTypeEnum::values());
            $table->enum('status', LeadStatusEnum::values())->default(LeadStatusEnum::NOUVEAU->value);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lead_import_id')->nullable()->constrained('lead_imports')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('phone');
            $table->index('email');
            $table->index('status');
            $table->index(['team_id', 'status']);
            $table->index(['assigned_to', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
