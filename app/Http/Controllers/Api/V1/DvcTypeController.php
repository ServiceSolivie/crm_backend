<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DvcType\DvcSchemaRequest;
use App\Http\Requests\DvcType\StoreDvcTypeRequest;
use App\Http\Requests\DvcType\UpdateDvcTypeRequest;
use App\Http\Resources\DvcTypeResource;
use App\Http\Resources\DvcTypeVersionResource;
use App\Models\DvcType;
use App\Services\DvcTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * DVC types: one per insurance type, whose JSON schema (form + PDF) the super
 * admin edits and publishes as versions.
 */
class DvcTypeController extends Controller
{
    public function __construct(protected DvcTypeService $dvcTypeService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DvcType::class);

        return $this->success(DvcTypeResource::collection($this->dvcTypeService->list($request->user())));
    }

    public function show(DvcType $dvcType): JsonResponse
    {
        $this->authorize('view', $dvcType);

        return $this->success(new DvcTypeResource($this->dvcTypeService->show($dvcType)));
    }

    public function store(StoreDvcTypeRequest $request): JsonResponse
    {
        $this->authorize('create', DvcType::class);

        $dvcType = $this->dvcTypeService->create($request->validated(), $request->user());

        return $this->created(new DvcTypeResource($dvcType), 'Type de DVC créé');
    }

    public function update(UpdateDvcTypeRequest $request, DvcType $dvcType): JsonResponse
    {
        $this->authorize('update', $dvcType);

        $dvcType = $this->dvcTypeService->update($dvcType, $request->validated());

        return $this->success(new DvcTypeResource($dvcType), 'Type de DVC mis à jour');
    }

    public function destroy(DvcType $dvcType): JsonResponse
    {
        $this->authorize('delete', $dvcType);

        $this->dvcTypeService->delete($dvcType);

        return $this->noContent('Type de DVC supprimé');
    }

    /**
     * Publish the schema as the type's next version.
     */
    public function publish(DvcSchemaRequest $request, DvcType $dvcType): JsonResponse
    {
        $this->authorize('update', $dvcType);

        $dvcType = $this->dvcTypeService->publish(
            $dvcType,
            $request->validated('schema'),
            $request->validated('change_note'),
            $request->user(),
        );

        return $this->created(new DvcTypeResource($dvcType), 'Nouvelle version publiée');
    }

    public function version(DvcType $dvcType, int $version): JsonResponse
    {
        $this->authorize('view', $dvcType);

        $dvcTypeVersion = $dvcType->versions()->with('creator:id,name')->where('version', $version)->firstOrFail();

        return $this->success(new DvcTypeVersionResource($dvcTypeVersion));
    }

    /**
     * Check a schema without saving it.
     */
    public function validateSchema(DvcSchemaRequest $request): JsonResponse
    {
        $this->authorize('create', DvcType::class);

        $this->dvcTypeService->assertValidSchema($request->validated('schema'));

        return $this->success(null, 'Schéma valide');
    }

    /**
     * The PDF of a schema filled with example values, without saving it.
     */
    public function preview(DvcSchemaRequest $request): Response
    {
        $this->authorize('create', DvcType::class);

        return response($this->dvcTypeService->preview($request->validated('schema')), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="apercu-dvc.pdf"',
        ]);
    }
}
