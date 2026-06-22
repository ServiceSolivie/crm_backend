<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Lead;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(protected PaymentService $paymentService) {}

    public function index(Request $request, Lead $lead): JsonResponse
    {
        $this->authorize('viewAny', [Payment::class, $lead]);

        $payments = $this->paymentService->listForLead($lead, (int) $request->integer('per_page', 15));

        return $this->success(PaymentResource::collection($payments));
    }

    public function store(StorePaymentRequest $request, Lead $lead): JsonResponse
    {
        $this->authorize('create', [Payment::class, $lead]);

        $payment = $this->paymentService->createPayment($lead, $request->validated(), $request->user());

        return $this->created(new PaymentResource($payment), 'Paiement enregistré avec succès');
    }

    public function destroy(Lead $lead, Payment $payment): JsonResponse
    {
        $this->authorize('delete', $payment);

        $this->paymentService->deletePayment($payment);

        return $this->noContent('Paiement supprimé avec succès');
    }
}
