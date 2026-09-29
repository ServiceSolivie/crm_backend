<?php

namespace App\Services;

use App\Mail\PaymentLinkMail;
use App\Models\PaymentSession;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * E-mails a payment link to the client. Best effort, like the other CRM
 * mails: when it fails, sent_at stays empty and the agent still has the
 * link to send it another way.
 */
class PaymentLinkSender
{
    public function send(PaymentSession $session): void
    {
        try {
            Mail::to($session->client_email)->send(new PaymentLinkMail($session));
            $session->update(['sent_at' => now()]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
