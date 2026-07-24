<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contract\GenerateContractRequest;
use App\Http\Resources\ContractResource;
use App\Models\Contract;
use App\Models\Lead;
use App\Services\ContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContractController extends Controller
{
    public function __construct(protected ContractService $contractService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Contract::class);

        $contracts = $this->contractService->paginateForUser(
            $request->user(),
            $request->only(['search', 'template_key', 'lead_id']),
            (int) $request->integer('per_page', 15),
        );

        return $this->success(ContractResource::collection($contracts));
    }

    public function templates(): JsonResponse
    {
        $this->authorize('viewAny', Contract::class);

        return $this->success($this->contractService->templates());
    }

    public function prefill(Request $request): JsonResponse
    {
        $lead = $request->filled('lead_id')
            ? Lead::query()->with('assignedAgent')->findOrFail($request->integer('lead_id'))
            : null;

        $this->authorize('generate', [Contract::class, $lead]);

        $values = $this->contractService->prefill(
            $request->string('template')->toString(),
            $lead,
            $request->user(),
        );

        return $this->success(['values' => $values]);
    }

    public function store(GenerateContractRequest $request): JsonResponse
    {
        $lead = $request->filled('lead_id')
            ? Lead::query()->findOrFail($request->integer('lead_id'))
            : null;

        $this->authorize('generate', [Contract::class, $lead]);

        $contract = $this->contractService->generate(
            $request->validated('template_key'),
            $request->validated('data'),
            $lead,
            $request->user(),
        );

        return $this->created(new ContractResource($contract), 'Contract generated successfully');
    }

    public function download(Contract $contract): StreamedResponse
    {
        $this->authorize('download', $contract);

        return $this->contractService->download($contract);
    }

    public function destroy(Contract $contract): JsonResponse
    {
        $this->authorize('delete', $contract);

        $this->contractService->delete($contract);

        return $this->noContent('Contract deleted successfully');
    }
}
