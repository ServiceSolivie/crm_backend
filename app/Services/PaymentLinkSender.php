<?php

namespace App\Services;

use App\Enums\ActivityEventEnum;
use App\Mail\PaymentLinkMail;
use App\Models\ActivityLog;
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
    public function __construct(protected ActivityLogger $activity) {}

    public function send(PaymentSession $session): void
    {
        try {
            Mail::to($session->client_email)->send(new PaymentLinkMail($session));
            $session->update(['sent_at' => now()]);

            $this->activity->payment($session, ActivityEventEnum::PAYMENT_LINK_EMAILED, "Envoyé à {$session->client_email}", [
                'to' => $session->client_email,
            ], ActivityLog::ACTOR_SYSTEM);
        } catch (Throwable $e) {
            report($e);

            $this->activity->payment($session, ActivityEventEnum::PAYMENT_LINK_EMAIL_FAILED, "L'e-mail à {$session->client_email} n'est pas parti : transmettez le lien au client.", [
                'to' => $session->client_email,
                'error' => mb_substr($e->getMessage(), 0, 300),
            ], ActivityLog::ACTOR_SYSTEM);
        }
    }
}
