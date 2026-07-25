<?php

namespace App\Console\Commands;

use App\Enums\AppointmentStatusEnum;
use App\Enums\ReminderChannelEnum;
use App\Models\AppointmentReminder;
use App\Notifications\AppointmentReminderNotification;
use Illuminate\Console\Command;

class SendDueAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-due-reminders';

    protected $description = 'Push a live notification for any due, unsent, in-app appointment reminder';

    protected const DEAD_STATUSES = [
        AppointmentStatusEnum::ANNULE->value,
        AppointmentStatusEnum::NON_VENU->value,
    ];

    public function handle(): int
    {
        $reminders = AppointmentReminder::query()
            ->whereNull('sent_at')
            ->where('channel', ReminderChannelEnum::IN_APP->value)
            ->where('remind_at', '<=', now())
            ->where('remind_at', '>=', now()->subMinutes(5))
            ->with(['appointment.lead', 'appointment.agent'])
            ->get();

        $sent = 0;
        $voided = 0;

        foreach ($reminders as $reminder) {
            $appointment = $reminder->appointment;

            if (! $appointment || ! $appointment->agent_id || in_array($appointment->status->value, self::DEAD_STATUSES, true)) {
                $reminder->update(['sent_at' => now()]);
                $voided++;

                continue;
            }

            $appointment->agent->notify(new AppointmentReminderNotification($appointment));
            $reminder->update(['sent_at' => now()]);
            $sent++;
        }

        $this->info("Sent {$sent} reminder(s), voided {$voided}, processed {$reminders->count()} due row(s).");

        return self::SUCCESS;
    }
}
