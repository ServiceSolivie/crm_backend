<?php

namespace Tests\Unit;

use App\Enums\LeadStatusEnum;
use App\Models\Lead;
use App\Models\LeadStatusHistory;
use App\Support\LeadStatusCatalog;
use PHPUnit\Framework\TestCase;

/**
 * Custom lead statuses without database: what the catalog answers for
 * system and custom codes, and what a lead in a custom status looks like
 * to the rules written against LeadStatusEnum.
 */
class LeadStatusCatalogTest extends TestCase
{
    private function catalog(): LeadStatusCatalog
    {
        $catalog = new LeadStatusCatalog;
        $catalog->setCustom([
            'DOCUMENTS_MANQUANTS' => ['label' => 'Documents manquants', 'stage' => 'follow_up', 'is_active' => true],
            'HORS_ZONE' => ['label' => 'Hors zone', 'stage' => 'lost', 'is_active' => true],
            'INJOIGNABLE_3_FOIS' => ['label' => 'Injoignable 3 fois', 'stage' => 'contact', 'is_active' => false],
        ]);

        return $catalog;
    }

    public function test_system_statuses_keep_their_label_and_stage(): void
    {
        $catalog = $this->catalog();

        foreach (LeadStatusEnum::cases() as $status) {
            $this->assertTrue($catalog->exists($status->value));
            $this->assertTrue($catalog->selectable($status->value));
            $this->assertFalse($catalog->isCustom($status->value));
            $this->assertSame($status->label(), $catalog->label($status->value));
            $this->assertSame($status->stage(), $catalog->stage($status->value));
        }
    }

    public function test_a_custom_status_is_a_label_and_a_stage(): void
    {
        $catalog = $this->catalog();

        $this->assertTrue($catalog->exists('HORS_ZONE'));
        $this->assertTrue($catalog->isCustom('HORS_ZONE'));
        $this->assertSame('Hors zone', $catalog->label('HORS_ZONE'));
        $this->assertSame('lost', $catalog->stage('HORS_ZONE'));
    }

    public function test_a_deactivated_status_still_exists_but_is_no_longer_offered(): void
    {
        $catalog = $this->catalog();

        $this->assertTrue($catalog->exists('INJOIGNABLE_3_FOIS'));
        $this->assertFalse($catalog->selectable('INJOIGNABLE_3_FOIS'));
        // Leads that already have it keep a readable label
        $this->assertSame('Injoignable 3 fois', $catalog->label('INJOIGNABLE_3_FOIS'));
    }

    public function test_an_unknown_code_is_refused_and_reads_as_itself(): void
    {
        $catalog = $this->catalog();

        $this->assertFalse($catalog->exists('SUPPRIME'));
        $this->assertFalse($catalog->selectable('SUPPRIME'));
        $this->assertFalse($catalog->selectable(null));
        $this->assertSame('SUPPRIME', $catalog->label('SUPPRIME'));
        $this->assertNull($catalog->stage('SUPPRIME'));
        $this->assertNull($catalog->label(null));
    }

    public function test_codes_list_the_system_statuses_first_then_every_custom_one(): void
    {
        $codes = $this->catalog()->codes();

        $this->assertSame(LeadStatusEnum::values(), array_slice($codes, 0, count(LeadStatusEnum::cases())));
        $this->assertSame(['DOCUMENTS_MANQUANTS', 'HORS_ZONE', 'INJOIGNABLE_3_FOIS'], array_slice($codes, count(LeadStatusEnum::cases())));
    }

    public function test_a_stage_holds_its_system_and_custom_statuses(): void
    {
        $catalog = $this->catalog();

        $this->assertSame(['PERDU', 'PAS_INTERESSE', 'MAUVAIS_NUMERO', 'LEAD_INVALIDE', 'HORS_ZONE'], $catalog->codesForStage('lost'));
        $this->assertSame(['PAS_DE_REPONSE', 'OCCUPE', 'INJOIGNABLE_3_FOIS'], $catalog->codesForStage('contact'));
        // Nothing custom can be placed in the validation circuit
        $this->assertSame(LeadStatusEnum::valuesForStage('won'), $catalog->codesForStage('won'));
        $this->assertNotContains('new', LeadStatusCatalog::CUSTOM_STAGES);
        $this->assertNotContains('won', LeadStatusCatalog::CUSTOM_STAGES);
    }

    public function test_a_lead_in_a_system_status_still_reads_as_the_enum(): void
    {
        $lead = (new Lead)->setRawAttributes(['status' => 'VALIDE']);

        $this->assertSame(LeadStatusEnum::VALIDE, $lead->status);
        $this->assertSame('VALIDE', $lead->status_code);
    }

    public function test_a_lead_in_a_custom_status_matches_no_system_rule(): void
    {
        $lead = (new Lead)->setRawAttributes(['status' => 'HORS_ZONE']);

        // Every rule compares with a named case: none of them is this lead
        $this->assertNull($lead->status);
        foreach (LeadStatusEnum::cases() as $status) {
            $this->assertFalse($lead->status === $status);
        }
        $this->assertFalse(in_array($lead->status, [LeadStatusEnum::PERDU, LeadStatusEnum::LEAD_INVALIDE], true));
        // The code itself is never lost
        $this->assertSame('HORS_ZONE', $lead->status_code);
    }

    public function test_a_status_can_be_written_as_an_enum_or_as_a_code(): void
    {
        $lead = new Lead;

        $lead->status = LeadStatusEnum::RAPPEL;
        $this->assertSame('RAPPEL', $lead->status_code);
        $this->assertSame(LeadStatusEnum::RAPPEL, $lead->status);

        $lead->status = 'DOCUMENTS_MANQUANTS';
        $this->assertSame('DOCUMENTS_MANQUANTS', $lead->status_code);
        $this->assertNull($lead->status);

        $lead->status = 'GESTION';
        $this->assertSame(LeadStatusEnum::GESTION, $lead->status);
    }

    public function test_history_rows_keep_custom_codes_on_both_sides(): void
    {
        $row = (new LeadStatusHistory)->setRawAttributes(['from_status' => 'DOCUMENTS_MANQUANTS', 'to_status' => 'GESTION']);

        $this->assertNull($row->from_status);
        $this->assertSame('DOCUMENTS_MANQUANTS', $row->from_status_code);
        $this->assertSame(LeadStatusEnum::GESTION, $row->to_status);
        $this->assertSame('GESTION', $row->to_status_code);

        $first = (new LeadStatusHistory)->setRawAttributes(['from_status' => null, 'to_status' => 'NOUVEAU']);
        $this->assertNull($first->from_status_code);
    }
}
