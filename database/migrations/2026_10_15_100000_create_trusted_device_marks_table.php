<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Remember this machine": after a PIN check, the browser holds a mark
     * that lets the next logins skip the PIN until it expires. Only its hash
     * is kept. A mark is exchanged for a new one at every login; a used mark
     * stays until it expires so that a copied one is recognised.
     */
    public function up(): void
    {
        Schema::create('trusted_device_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trusted_device_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            // Date-times, not timestamps: MySQL would update the first timestamp
            // column on every change of the row, moving the expiry when a mark is used
            $table->dateTime('expires_at')->index();
            $table->dateTime('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trusted_device_marks');
    }
};
