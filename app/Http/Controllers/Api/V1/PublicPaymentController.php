<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentSessionStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\PaymentSession;
use App\Services\PaymentSessionService;
use App\Services\PaymentSyncService;
use Illuminate\Http\JsonResponse;

/**
 * Public result page of a payment (the Hyperswitch return_url). No login:
 * the unguessable token in the URL is the only key. It only tells the
 * client whether the payment went through: no name, e-mail or lead data.
 * The page expires some time after the client came back (410).
 */
class PublicPaymentController extends Controller
{
    public function __construct(
        protected PaymentSessionService $sessionService,
        protected PaymentSyncService $sync,
    ) {}

    /**
     * POST /public/payments/{token}/return — the page opened (client back
     * from the bank): one normal status check, a forced one is armed if
     * the status is not final yet.
     */
    public function return(string $token): JsonResponse
    {
        $session = $this->find($token);
        if ($session instanceof JsonResponse) {
            return $session;
        }

        return $this->respond($this->sync->onClientReturn($session));
    }

    /**
     * GET /public/payments/{token} — polling: the saved state, plus the
     * single armed forced sync once it is due.
     */
    public function show(string $token): JsonResponse
    {
        $session = $this->find($token);
        if ($session instanceof JsonResponse) {
            return $session;
        }

        return $this->respond($this->sync->runDueForcedSync($session));
    }

    protected function find(string $token): PaymentSession|JsonResponse
    {
        $session = strlen($token) === 48 ? $this->sessionService->findByToken($token) : null;

        if (! $session) {
            return $this->error('Lien de paiement introuvable.', 404);
        }

        if ($this->sessionService->resultPageExpired($session)) {
            return $this->error('Ce lien a expiré.', 410, ['status' => 'link_expired']);
        }

        return $session;
    }

    protected function respond(PaymentSession $session): JsonResponse
    {
        return $this->success([
            'reference' => $session->reference,
            'amount' => $session->amount,
            'currency' => $session->currency,
            'status' => match ($session->status) {
                PaymentSessionStatusEnum::PAYEE => 'paid',
                PaymentSessionStatusEnum::ECHOUEE => 'failed',
                PaymentSessionStatusEnum::ANNULEE => 'cancelled',
                PaymentSessionStatusEnum::EXPIREE => 'expired',
                default => 'pending',
            },
            'company' => config('app.name'),
        ])->withHeaders([
            'Cache-Control' => 'no-store',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
