<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lead sources a marketing user is limited to (none = all sources).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_source_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_source_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'lead_source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_source_user');
    }
};
