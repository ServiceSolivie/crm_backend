<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scalable DVC: one DVC type per insurance type, its form + PDF described by
 * a JSON schema the super admin edits. Every save of the schema is a new,
 * immutable version; a generated DVC (contract) keeps the version it was
 * filled with.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dvc_types', function (Blueprint $table) {
            $table->id();
            $table->string('insurance_type', 40)->unique();
            $table->string('label', 120);
            $table->string('filename_prefix', 40)->default('DVC');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('dvc_type_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dvc_type_id')->constrained('dvc_types')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('schema');
            $table->string('change_note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['dvc_type_id', 'version']);
        });

        Schema::table('dvc_types', function (Blueprint $table) {
            $table->foreign('current_version_id')->references('id')->on('dvc_type_versions')->nullOnDelete();
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->foreignId('dvc_type_version_id')->nullable()->after('version')->constrained('dvc_type_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dvc_type_version_id');
        });

        Schema::table('dvc_types', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });

        Schema::dropIfExists('dvc_type_versions');
        Schema::dropIfExists('dvc_types');
    }
};
