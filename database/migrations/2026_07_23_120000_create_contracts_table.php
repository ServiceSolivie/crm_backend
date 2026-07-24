<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('template_key');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->string('client_name');
            $table->json('data');
            $table->string('original_filename');
            $table->string('file_path');
            $table->string('mime_type', 100)->default('application/pdf');
            $table->unsignedBigInteger('file_size');
            $table->foreignId('generated_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['lead_id', 'template_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
