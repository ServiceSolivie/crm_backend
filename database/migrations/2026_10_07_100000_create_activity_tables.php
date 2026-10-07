<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Activity journal, in two levels:
 *  - activity_operations: one row per operation (a payment, a Google Ads
 *    submission, an account's logins of the day, a managed user…), with how
 *    it stands now;
 *  - activity_logs: everything that happened to it, in order.
 *
 * Never holds a secret (API key, password, client_secret) nor a full
 * provider payload: only identifiers, statuses, codes and messages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_operations', function (Blueprint $table) {
            $table->id();
            // What makes it one operation: "session:124", "gads:<google lead id>", "auth:<email>:<day>"…
            $table->string('key', 191)->unique();
            $table->string('category', 20)->index();
            // A payment operation that contains refund logs
            $table->boolean('has_refund')->default(false)->index();
            $table->string('title');
            $table->string('subtitle')->nullable();
            // What the search uses: PAY-33509-5, Google lead id, e-mail
            $table->string('reference', 191)->nullable()->index();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->nullableMorphs('subject');
            $table->string('state', 20)->default('pending')->index();
            $table->string('state_label')->nullable();
            // The event that left it in this state (the interface translates it)
            $table->string('state_event', 60)->nullable();
            $table->unsignedInteger('logs_count')->default(0);
            $table->unsignedInteger('problems_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamps();

            $table->index(['category', 'last_activity_at']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_operation_id')->constrained('activity_operations')->cascadeOnDelete();
            $table->string('event', 60)->index();
            $table->string('category', 20);
            $table->string('level', 10)->index();
            $table->string('message', 500)->nullable();
            // user (actor_id), system (scheduled job), client (public page), google_ads (webhook)
            $table->string('actor_type', 20)->default('system');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['activity_operation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('activity_operations');
    }
};
