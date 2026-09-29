<?php

namespace App\Mail;

use App\Models\PaymentSession;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the client: the secure link to pay the amount of a payment session.
 */
class PaymentLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PaymentSession $session) {}

    public function build(): self
    {
        $lead = $this->session->lead;
        $clientName = trim(($lead->first_name ?? '').' '.($lead->last_name ?? ''));

        return $this
            ->subject("Votre lien de paiement — {$this->session->reference}")
            ->view('mail.payment-link', [
                'session' => $this->session,
                'clientName' => $clientName,
                'amount' => number_format((float) $this->session->amount, 2, ',', ' ').' €',
                'companyName' => config('app.name'),
            ]);
    }
}
