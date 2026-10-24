<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Requests received on the marketing intake endpoint, kept exactly as
     * they arrived (test phase: no lead is created from them). The content
     * is only kept when the key was right.
     */
    public function up(): void
    {
        Schema::create('intake_requests', function (Blueprint $table) {
            $table->id();
            // dateTime, not timestamp: MySQL must never rewrite it
            $table->dateTime('received_at')->index();
            $table->string('method', 10);
            $table->string('content_type')->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->longText('body')->nullable();
            $table->json('query')->nullable();
            $table->json('headers')->nullable();
            $table->string('ip', 45)->nullable();
            $table->boolean('key_valid')->default(false);
            $table->string('key_via', 10)->nullable(); // header | query
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intake_requests');
    }
};
