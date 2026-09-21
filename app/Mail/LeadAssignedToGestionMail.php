<?php

namespace App\Mail;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LeadAssignedToGestionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Lead $lead, public ?User $sender = null) {}

    public function build(): self
    {
        $leadName = trim(($this->lead->first_name ?? '').' '.($this->lead->last_name ?? '')) ?: $this->lead->reference;

        return $this
            ->subject("Nouveau lead à traiter : {$leadName}")
            ->view('mail.lead-assigned-to-gestion', [
                'lead' => $this->lead,
                'leadName' => $leadName,
                'senderName' => $this->sender?->name,
                'leadUrl' => rtrim(config('app.frontend_url'), '/')."/leads/{$this->lead->id}",
            ]);
    }
}
