<?php

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LeadNeedsCorrectionMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, string>  $issues
     */
    public function __construct(public Lead $lead, public array $issues) {}

    public function build(): self
    {
        $leadName = trim(($this->lead->first_name ?? '').' '.($this->lead->last_name ?? '')) ?: $this->lead->reference;

        return $this
            ->subject("Lead à corriger : {$leadName}")
            ->view('mail.lead-needs-correction', [
                'lead' => $this->lead,
                'leadName' => $leadName,
                'issues' => $this->issues,
                'leadUrl' => rtrim(config('app.frontend_url'), '/')."/leads/{$this->lead->id}",
            ]);
    }
}
