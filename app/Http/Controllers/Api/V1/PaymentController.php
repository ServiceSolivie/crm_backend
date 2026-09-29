<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\UpdateContractTotalRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Lead;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Payments of a lead (read-only: they come from Hyperswitch, see
 * PaymentSessionController) and its contract total.
 */
class PaymentController extends Controller
{
    public function __construct(protected PaymentService $paymentService) {}

    public function index(Request $request, Lead $lead): JsonResponse
    {
        $this->authorize('viewAny', [Payment::class, $lead]);

        $payments = $this->paymentService->listForLead($lead, (int) $request->integer('per_page', 15));

        return $this->success(PaymentResource::collection($payments));
    }

    /**
     * PATCH /leads/{lead}/contract-total { total, reason }
     * E.g. raise the total to ask an additional payment when the contract grew.
     */
    public function updateTotal(UpdateContractTotalRequest $request, Lead $lead): JsonResponse
    {
        $this->authorize('updateTotal', [Payment::class, $lead]);

        $lead = $this->paymentService->updateContractTotal(
            $lead,
            $request->user(),
            $request->validated('total'),
            $request->validated('reason'),
        );

        return $this->success([
            'expected_revenue' => $lead->expected_revenue,
            'total_received' => $lead->total_received,
            'remaining_amount' => $lead->remaining_amount,
            'payment_status' => $lead->payment_status,
        ], 'Montant total du contrat mis à jour');
    }
}
