<?php

namespace App\Services;

use App\Enums\ClientTypeEnum;
use App\Enums\DocumentTypeEnum;
use App\Enums\InsuranceTypeEnum;

class DossierRequirementsConfig
{
    /**
     * Get the required documents for a given insurance type and client type.
     *
     * Returns null when client_type is required but not provided (AUTO, MOTO).
     * Returns an empty array for insurance types without defined requirements.
     *
     * @return DocumentTypeEnum[]|null
     */
    public static function getRequiredDocuments(InsuranceTypeEnum $insuranceType, ?ClientTypeEnum $clientType): ?array
    {
        return match ($insuranceType) {
            InsuranceTypeEnum::AUTO => self::vehicleDocuments($clientType),
            InsuranceTypeEnum::MOTO => self::vehicleDocuments($clientType),
            InsuranceTypeEnum::RC_PRO => self::rcProDocuments(),
            InsuranceTypeEnum::DECENNALE => self::decennaleDocuments(),
            default => [],
        };
    }

    public static function requiresClientType(InsuranceTypeEnum $insuranceType): bool
    {
        return in_array($insuranceType, [InsuranceTypeEnum::AUTO, InsuranceTypeEnum::MOTO]);
    }

    /**
     * @return DocumentTypeEnum[]|null
     */
    private static function vehicleDocuments(?ClientTypeEnum $clientType): ?array
    {
        if ($clientType === null) {
            return null;
        }

        $base = [
            DocumentTypeEnum::CARTE_IDENTITE,
            DocumentTypeEnum::PERMIS_CONDUIRE,
            DocumentTypeEnum::CARTE_GRISE,
            DocumentTypeEnum::RIB,
            DocumentTypeEnum::RELEVE_INFORMATION,
        ];

        if ($clientType === ClientTypeEnum::PROFESSIONAL) {
            $base[] = DocumentTypeEnum::EXTRAIT_KBIS;
        }

        return $base;
    }

    /**
     * @return DocumentTypeEnum[]
     */
    private static function rcProDocuments(): array
    {
        return [
            DocumentTypeEnum::EXTRAIT_KBIS,
            DocumentTypeEnum::CARTE_IDENTITE,
            DocumentTypeEnum::NUMERO_SIRET,
        ];
    }

    /**
     * @return DocumentTypeEnum[]
     */
    private static function decennaleDocuments(): array
    {
        return [
            DocumentTypeEnum::EXTRAIT_KBIS,
            DocumentTypeEnum::CONTRAT_CHANTIER,
            DocumentTypeEnum::CARTE_IDENTITE,
            DocumentTypeEnum::RIB,
        ];
    }
}
