<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Agent's team at the time of the call, so team visibility survives reassignments.
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();

            $table->string('ringover_call_id', 64)->nullable()->unique();
            $table->string('ringover_channel_id', 64)->nullable();

            $table->enum('direction', ['in', 'out'])->nullable();
            $table->enum('status', ['initiated', 'ringing', 'answered', 'completed', 'missed', 'no_answer', 'voicemail'])
                ->default('ringing');
            $table->boolean('is_internal')->default(false);

            $table->string('from_number', 20)->nullable();
            $table->string('to_number', 20)->nullable();
            // The external party (lead side), used for matching and unmatched-call lists.
            $table->string('contact_number', 20)->nullable()->index();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('talk_seconds')->nullable();

            $table->text('recording_url')->nullable();
            $table->unsignedInteger('recording_duration_seconds')->nullable();
            $table->text('voicemail_url')->nullable();
            $table->text('transcription_url')->nullable();
            $table->longText('ai_summary')->nullable();

            $table->text('note')->nullable();
            $table->enum('source', ['webhook', 'sync', 'crm'])->default('webhook');
            $table->string('last_event', 40)->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'started_at']);
            $table->index(['user_id', 'started_at']);
            $table->index(['team_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calls');
    }
};
