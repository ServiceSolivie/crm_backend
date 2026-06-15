<?php

use App\Enums\ReminderChannelEnum;
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
        Schema::create('appointment_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->dateTime('remind_at');
            $table->enum('channel', ReminderChannelEnum::values())->default(ReminderChannelEnum::IN_APP->value);
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();

            $table->index('remind_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointment_reminders');
    }
};
