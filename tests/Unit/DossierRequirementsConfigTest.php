<?php

namespace Tests\Unit;

use App\Enums\ClientTypeEnum;
use App\Enums\DocumentTypeEnum;
use App\Enums\InsuranceTypeEnum;
use App\Services\DossierRequirementsConfig;
use PHPUnit\Framework\TestCase;

class DossierRequirementsConfigTest extends TestCase
{
    public function test_auto_insurance_without_client_type_returns_null(): void
    {
        $result = DossierRequirementsConfig::getRequiredDocuments(InsuranceTypeEnum::AUTO, null);

        $this->assertNull($result);
    }

    public function test_professional_auto_insurance_requires_kbis_document(): void
    {
        $result = DossierRequirementsConfig::getRequiredDocuments(
            InsuranceTypeEnum::AUTO,
            ClientTypeEnum::PROFESSIONAL
        );

        $this->assertContains(DocumentTypeEnum::EXTRAIT_KBIS, $result);
    }

    public function test_only_auto_and_moto_require_a_client_type(): void
    {
        $this->assertTrue(DossierRequirementsConfig::requiresClientType(InsuranceTypeEnum::AUTO));
        $this->assertTrue(DossierRequirementsConfig::requiresClientType(InsuranceTypeEnum::MOTO));
        $this->assertFalse(DossierRequirementsConfig::requiresClientType(InsuranceTypeEnum::RC_PRO));
    }
}
