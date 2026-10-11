<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use App\Services\GoogleSheetLeadImporter;
use App\Services\GoogleSheetsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GoogleSheetMissingRowsCatchUpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create(['email' => 'akkaoui@crm.test']);
    }

    protected function mockSheet(): Mockery\MockInterface
    {
        $sheet = Mockery::mock(GoogleSheetsService::class);
        $this->app->instance(GoogleSheetsService::class, $sheet);

        return $sheet;
    }

    protected function importer(): GoogleSheetLeadImporter
    {
        return $this->app->make(GoogleSheetLeadImporter::class);
    }

    protected function webhookPayload(int $row, string $phone): array
    {
        return [
            'sheet' => 'Lead',
            'row' => $row,
            'action' => 'new',
            'fullname' => "Client {$row}",
            'phone' => $phone,
            'insurance_type' => 'auto',
        ];
    }

    /** Lead sheet columns: source, name, email, phone, postal, insurance_type, agent, doublon, date */
    protected function leadSheetRow(string $name, string $phone): array
    {
        return ['fb', $name, '', "p:{$phone}", 'z:75001', 'Auto', '', '', ''];
    }

    public function test_next_row_is_imported_without_reading_the_sheet(): void
    {
        $sheet = $this->mockSheet();
        $sheet->shouldNotReceive('getRows');

        $this->importer()->importFromWebhookPayload($this->webhookPayload(10, '0600000010'));
        $this->importer()->importFromWebhookPayload($this->webhookPayload(11, '0600000011'));

        $this->assertSame(['Lead:10', 'Lead:11'], Lead::orderBy('id')->pluck('sheet_row_key')->all());
    }

    public function test_missing_rows_are_imported_before_the_incoming_row(): void
    {
        $sheet = $this->mockSheet();
        $sheet->shouldReceive('getRows')->once()->with('Lead', 11, 13)->andReturn([
            $this->leadSheetRow('Client 11', '0600000011'),
            $this->leadSheetRow('Client 12', '0600000012'),
            $this->leadSheetRow('Client 13', '0600000013'),
        ]);

        $this->importer()->importFromWebhookPayload($this->webhookPayload(10, '0600000010'));
        $this->importer()->importFromWebhookPayload($this->webhookPayload(14, '0600000014'));

        $this->assertSame(
            ['Lead:10', 'Lead:11', 'Lead:12', 'Lead:13', 'Lead:14'],
            Lead::orderBy('id')->pluck('sheet_row_key')->all(),
        );

        $caughtUp = Lead::where('sheet_row_key', 'Lead:12')->first();
        $this->assertSame('0600000012', $caughtUp->phone);
        $this->assertSame('75001', $caughtUp->city);
    }

    public function test_last_row_is_compared_numerically_and_per_sheet(): void
    {
        $sheet = $this->mockSheet();
        // Compared as strings, "99" would beat "100" and rows 100-101 would be re-fetched.
        $sheet->shouldReceive('getRows')->once()->with('Lead', 101, 101)->andReturn([
            $this->leadSheetRow('Client 101', '0600000101'),
        ]);

        $this->importer()->importFromWebhookPayload($this->webhookPayload(99, '0600000099'));
        $this->importer()->importFromWebhookPayload($this->webhookPayload(100, '0600000100'));
        // Another sheet's higher row numbers must not count.
        $this->importer()->importFromWebhookPayload(['sheet' => 'Decennale', 'row' => 500, 'fullname' => 'Pro', 'phone' => '0700000500'], catchUp: false);
        $this->importer()->importFromWebhookPayload($this->webhookPayload(102, '0600000102'));

        $this->assertNotNull(Lead::where('sheet_row_key', 'Lead:101')->first());
    }

    public function test_no_catch_up_when_the_sheet_has_no_row_in_the_crm_yet(): void
    {
        $sheet = $this->mockSheet();
        $sheet->shouldNotReceive('getRows');

        $this->importer()->importFromWebhookPayload($this->webhookPayload(250, '0600000250'));

        $this->assertSame(1, Lead::count());
    }

    public function test_sheet_read_failure_does_not_block_the_incoming_row(): void
    {
        $sheet = $this->mockSheet();
        $sheet->shouldReceive('getRows')->andThrow(new \RuntimeException('quota exceeded'));

        $this->importer()->importFromWebhookPayload($this->webhookPayload(10, '0600000010'));
        $result = $this->importer()->importFromWebhookPayload($this->webhookPayload(15, '0600000015'));

        $this->assertSame('created', $result['status']);
        $this->assertSame(['Lead:10', 'Lead:15'], Lead::orderBy('id')->pluck('sheet_row_key')->all());
    }

    public function test_deleted_lead_row_is_not_brought_back(): void
    {
        $sheet = $this->mockSheet();
        $sheet->shouldNotReceive('getRows');

        $this->importer()->importFromWebhookPayload($this->webhookPayload(10, '0600000010'));
        $this->importer()->importFromWebhookPayload($this->webhookPayload(11, '0600000011'));
        Lead::where('sheet_row_key', 'Lead:11')->first()->delete();

        $this->importer()->importFromWebhookPayload($this->webhookPayload(12, '0600000012'));

        $this->assertSame(['Lead:10', 'Lead:12'], Lead::orderBy('id')->pluck('sheet_row_key')->all());
    }
}
