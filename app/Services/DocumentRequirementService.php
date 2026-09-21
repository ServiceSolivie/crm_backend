<?php

namespace App\Services;

use App\Enums\ClientTypeEnum;
use App\Enums\DocumentTypeEnum;
use App\Enums\InsuranceTypeEnum;
use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Repositories\Contracts\DocumentTypeRepositoryInterface;

class DocumentRequirementService
{
    public function __construct(protected DocumentTypeRepositoryInterface $documentTypes) {}

    /**
     * Get the required documents for a given insurance type and client type.
     *
     * Returns null when client_type is required but not provided.
     * Returns an empty array for insurance types without defined requirements.
     *
     * @return array<int, array{name: string, label: string}>|null
     */
    public function getRequiredDocuments(InsuranceTypeEnum $insuranceType, ?ClientTypeEnum $clientType): ?array
    {
        if ($this->requiresClientType($insuranceType) && $clientType === null) {
            return null;
        }

        $query = DocumentRequirement::query()
            ->join('document_types', 'document_types.id', '=', 'document_requirements.document_type_id')
            ->where('document_types.is_active', true)
            ->where('document_requirements.insurance_type', $insuranceType->value);

        if ($clientType !== null) {
            $query->where(function ($q) use ($clientType) {
                $q->where('document_requirements.client_type', $clientType->value)
                    ->orWhereNull('document_requirements.client_type');
            });
        } else {
            $query->whereNull('document_requirements.client_type');
        }

        $documents = $query
            ->orderBy('document_types.sort_order')
            ->orderBy('document_types.name')
            ->select('document_types.name', 'document_types.label')
            ->distinct()
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'label' => $row->label])
            ->reject(fn ($doc) => $doc['name'] === DocumentTypeEnum::DVC->value)
            ->values()
            ->all();

        return [self::dvcDocument(), ...$documents];
    }

    /**
     * The signed DVC is needed for every product, whatever the admin matrix
     * says: it always comes first in the dossier.
     *
     * @return array{name: string, label: string}
     */
    public static function dvcDocument(): array
    {
        static $label = null;
        $label ??= DocumentType::query()->where('name', DocumentTypeEnum::DVC->value)->value('label')
            ?? DocumentTypeEnum::DVC->label();

        return ['name' => DocumentTypeEnum::DVC->value, 'label' => $label];
    }

    public function requiresClientType(InsuranceTypeEnum $insuranceType): bool
    {
        return DocumentRequirement::query()
            ->where('insurance_type', $insuranceType->value)
            ->whereNotNull('client_type')
            ->exists();
    }

    /**
     * Get the full requirements matrix for the admin UI.
     */
    public function getRequirementsMatrix(): array
    {
        // DVC is required everywhere by code, not configurable in the matrix
        $allTypes = $this->documentTypes->activeOrdered()
            ->reject(fn (DocumentType $t) => $t->name === DocumentTypeEnum::DVC->value)
            ->values();
        $allRequirements = DocumentRequirement::all();

        $matrix = [];

        foreach (InsuranceTypeEnum::cases() as $insurance) {
            $requiresClient = $this->requiresClientType($insurance);

            $entry = [
                'insurance_type' => $insurance->value,
                'insurance_type_label' => $insurance->label(),
                'requires_client_type' => $requiresClient,
                'groups' => [],
            ];

            if ($requiresClient) {
                foreach (ClientTypeEnum::cases() as $clientType) {
                    $entry['groups'][] = $this->buildGroup(
                        $allRequirements, $allTypes, $insurance->value, $clientType->value, $clientType->label()
                    );
                }
            } else {
                $entry['groups'][] = $this->buildGroup(
                    $allRequirements, $allTypes, $insurance->value, null, null
                );
            }

            $matrix[] = $entry;
        }

        return [
            'document_types' => $allTypes->map(fn (DocumentType $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'label' => $t->label,
            ])->values()->all(),
            'matrix' => $matrix,
            // Required for every product by the code (shown locked in the matrix)
            'always_required' => $this->documentTypes->newQuery()
                ->where('name', DocumentTypeEnum::DVC->value)
                ->get()
                ->map(fn (DocumentType $t) => ['id' => $t->id, 'name' => $t->name, 'label' => $t->label])
                ->values()
                ->all(),
        ];
    }

    public function syncRequirements(string $insuranceType, ?string $clientType, array $documentTypeIds): void
    {
        DocumentRequirement::query()
            ->where('insurance_type', $insuranceType)
            ->when($clientType !== null,
                fn ($q) => $q->where('client_type', $clientType),
                fn ($q) => $q->whereNull('client_type'),
            )
            ->delete();

        $rows = array_map(fn (int $id) => [
            'insurance_type' => $insuranceType,
            'client_type' => $clientType,
            'document_type_id' => $id,
            'created_at' => now(),
            'updated_at' => now(),
        ], $documentTypeIds);

        if (! empty($rows)) {
            DocumentRequirement::insert($rows);
        }
    }

    private function buildGroup($allRequirements, $allTypes, string $insuranceType, ?string $clientType, ?string $clientTypeLabel): array
    {
        $reqTypeIds = $allRequirements
            ->filter(function ($r) use ($insuranceType, $clientType) {
                if ($r->insurance_type->value !== $insuranceType) {
                    return false;
                }

                return $clientType === null
                    ? $r->client_type === null
                    : ($r->client_type?->value === $clientType || $r->client_type === null);
            })
            ->pluck('document_type_id')
            ->all();

        return [
            'client_type' => $clientType,
            'client_type_label' => $clientTypeLabel,
            'document_type_ids' => array_values(array_unique($reqTypeIds)),
        ];
    }
}
