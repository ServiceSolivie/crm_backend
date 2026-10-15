<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Machines allowed to open the CRM. Each row is a passkey created by
     * Windows Hello on one PC: only its public half is kept here, the secret
     * half never leaves the machine.
     */
    public function up(): void
    {
        Schema::create('trusted_devices', function (Blueprint $table) {
            $table->id();
            $table->string('label', 80)->nullable();
            // Base64url id of the passkey, as the browser gives it
            $table->string('credential_id', 512)->unique();
            $table->text('public_key');
            $table->unsignedBigInteger('sign_count')->default(0);
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason', 40)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->foreignId('last_used_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('last_ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();
        });

        // Small key/value store for switches the super admin flips from the CRM
        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('trusted_devices');
    }
};
