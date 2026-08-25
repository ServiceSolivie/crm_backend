<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('login_url');
            $table->string('domain')->index();
            $table->json('field_mapping')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->string('label');
            $table->text('username_encrypted')->nullable();
            $table->text('email_encrypted')->nullable();
            $table->text('password_encrypted')->nullable();
            $table->text('extra_fields_encrypted')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_credential', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('credential_id')->constrained('credentials')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'credential_id']);
        });

        Schema::create('vault_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('credential_id')->nullable()->constrained('credentials')->nullOnDelete();
            $table->string('action');
            $table->string('domain')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vault_audit_logs');
        Schema::dropIfExists('user_credential');
        Schema::dropIfExists('credentials');
        Schema::dropIfExists('partners');
    }
};
