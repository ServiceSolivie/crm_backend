<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentSessionStatusEnum;
use App\Filters\PaymentSessionFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StorePaymentSessionRequest;
use App\Http\Resources\PaymentSessionResource;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\PaymentSession;
use App\Services\PaymentSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Online payment requests of a lead (Hyperswitch payment links).
 */
class PaymentSessionController extends Controller
{
    public function __construct(protected PaymentSessionService $sessionService) {}

    /**
     * GET /payment-sessions — Payments page (filters: PaymentSessionFilter)
     */
    public function all(Request $request, PaymentSessionFilter $filters): JsonResponse
    {
        $this->authorize('viewList', Payment::class);

        $sessions = $this->sessionService->paginateForUser(
            $request->user(),
            $filters,
            min(100, max(1, (int) $request->integer('per_page', 15))),
        );

        return $this->success(PaymentSessionResource::collection($sessions));
    }

    public function index(Lead $lead): JsonResponse
    {
        $this->authorize('viewAny', [Payment::class, $lead]);

        return $this->success(PaymentSessionResource::collection($this->sessionService->listForLead($lead)));
    }

    /**
     * POST /leads/{lead}/payment-sessions { amount, email? }
     */
    public function store(StorePaymentSessionRequest $request, Lead $lead): JsonResponse
    {
        $this->authorize('managePaymentLinks', [Payment::class, $lead]);

        $session = $this->sessionService->create(
            $lead,
            $request->user(),
            $request->validated('amount'),
            $request->validated('email'),
        );

        // No clear answer from Hyperswitch: 202, the request is kept to be checked
        if ($session->status === PaymentSessionStatusEnum::A_VERIFIER) {
            return $this->success(
                new PaymentSessionResource($session),
                'Hyperswitch n\'a pas confirmé la création : la demande est à vérifier avant d\'envoyer un nouveau lien.',
                202,
            );
        }

        return $this->created(new PaymentSessionResource($session), $this->sentMessage($session));
    }

    /**
     * POST /leads/{lead}/payment-sessions/{paymentSession}/verify
     * Asks Hyperswitch for the current status of a pending request (a link
     * waiting for the client, or a request "à vérifier").
     */
    public function verify(Lead $lead, PaymentSession $paymentSession): JsonResponse
    {
        $this->authorize('managePaymentLinks', [Payment::class, $lead]);

        $wasToCheck = $paymentSession->status === PaymentSessionStatusEnum::A_VERIFIER;
        $session = $this->sessionService->verify($paymentSession);

        $message = match ($session->status) {
            PaymentSessionStatusEnum::PAYEE => 'Paiement reçu.',
            PaymentSessionStatusEnum::ECHOUEE => 'Le paiement a échoué : vous pouvez envoyer un nouveau lien.',
            PaymentSessionStatusEnum::EXPIREE => 'Le lien de paiement a expiré : vous pouvez en envoyer un nouveau.',
            PaymentSessionStatusEnum::ANNULEE => $wasToCheck
                ? 'Le paiement n\'a pas été créé chez Hyperswitch : vous pouvez envoyer un nouveau lien.'
                : 'Le paiement a été annulé chez Hyperswitch.',
            PaymentSessionStatusEnum::OUVERTE => $wasToCheck ? $this->sentMessage($session) : 'Le client n\'a pas encore payé.',
            default => 'Demande de paiement vérifiée.',
        };

        return $this->success(new PaymentSessionResource($session), $message);
    }

    /**
     * POST /leads/{lead}/payment-sessions/{paymentSession}/cancel
     */
    public function cancel(Lead $lead, PaymentSession $paymentSession): JsonResponse
    {
        $this->authorize('managePaymentLinks', [Payment::class, $lead]);

        return $this->success(new PaymentSessionResource($this->sessionService->cancel($paymentSession)), 'Lien de paiement annulé');
    }

    protected function sentMessage(PaymentSession $session): string
    {
        return $session->sent_at
            ? 'Lien de paiement envoyé au client'
            : 'Lien de paiement créé, mais l\'e-mail n\'a pas pu être envoyé : transmettez le lien au client.';
    }
}
