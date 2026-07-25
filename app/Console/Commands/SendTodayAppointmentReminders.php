<?php

namespace App\Console\Commands;

use App\Enums\AppointmentStatusEnum;
use App\Models\Appointment;
use App\Notifications\TodayAppointmentsNotification;
use Illuminate\Console\Command;

class SendTodayAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-today-reminders';

    protected $description = "Notify each agent who has an appointment scheduled today (once per day, per agent)";

    public function handle(): int
    {
        $appointments = Appointment::query()
            ->whereDate('scheduled_at', today())
            ->whereNotIn('status', [
                AppointmentStatusEnum::ANNULE->value,
                AppointmentStatusEnum::NON_VENU->value,
            ])
            ->whereNotNull('agent_id')
            ->with(['lead', 'agent'])
            ->get()
            ->groupBy('agent_id');

        $notified = 0;
        $skipped = 0;

        foreach ($appointments as $agentAppointments) {
            $agent = $agentAppointments->first()->agent;

            if (! $agent) {
                continue;
            }

            $alreadySent = $agent->notifications()
                ->where('type', TodayAppointmentsNotification::class)
                ->whereDate('created_at', today())
                ->exists();

            if ($alreadySent) {
                $skipped++;

                continue;
            }

            $agent->notify(new TodayAppointmentsNotification($agentAppointments));
            $notified++;
        }

        $this->info("Notified {$notified} agent(s), skipped {$skipped} (already notified today).");

        return self::SUCCESS;
    }
}
