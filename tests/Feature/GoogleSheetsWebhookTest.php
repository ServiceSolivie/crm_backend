<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use App\Services\LeadService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

/**
 * What the Apps Script's POST stores: the sheet (`feuille`) and the
 * sheet-specific `extra` fields end up as one flat JSON object in
 * Lead::comment, and rows resent by the catch-up (`rattrapage`) are
 * logged and never duplicated.
 *
 * The project's migrations are MySQL-only, so this builds just the tables
 * the import touches, and LeadService::createLead() is replaced by a plain
 * insert (its reference numbering and history need the full schema).
 */
class GoogleSheetsWebhookTest extends TestCase
{
    private const SECRET = 'test-secret';

    private int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google.webhook_secret' => self::SECRET]);

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('reference');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->string('insurance_type')->nullable();
            $table->string('status')->nullable();
            $table->unsignedBigInteger('lead_source_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->text('comment')->nullable();
            $table->timestamp('lead_submitted_at')->nullable();
            $table->string('sheet_row_key')->nullable();
            $table->boolean('is_doublon')->default(false);
            $table->unsignedBigInteger('doublon_of_lead_id')->nullable();
            $table->string('dvc_status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // The importer's "system user" that creates every webhook lead.
        DB::table('users')->insert(['name' => 'System', 'email' => 'akkaoui@crm.test']);

        $leadService = Mockery::mock(LeadService::class);
        $leadService->shouldReceive('createLead')->andReturnUsing(function (array $data, User $creator) {
            $this->n++;

            return Lead::forceCreate($data + ['reference' => "TEST-{$this->n}", 'created_by' => $creator->id]);
        });
        $leadService->shouldReceive('findRecentDoublon')->andReturnNull();
        $this->app->instance(LeadService::class, $leadService);
    }

    private function send(array $payload)
    {
        return $this->postJson('/api/v1/webhooks/google-sheets/leads', $payload, ['X-Webhook-Secret' => self::SECRET]);
    }

    /** A row as the Apps Script's WH_payload() builds it. */
    private function row(string $sheet, int $row, array $overrides = []): array
    {
        return array_merge([
            'sheet' => $sheet,
            'row' => (string) $row,
            'full_name' => 'Jean Dupont',
            'phone' => '06000'.str_pad((string) $row, 5, '0', STR_PAD_LEFT),
            'email' => "lead{$row}@example.com",
            'postal' => '75001',
            'address' => '',
            'source' => 'TikTok',
            'insurance_type' => 'Auto',
            'agent' => '',
            'date' => '2026-10-07T10:00:00.000Z',
            'action' => 'new',
            'doublon' => false,
            'old_agent' => '',
            'extra' => [],
        ], $overrides);
    }

    private function comment(Lead $lead): array
    {
        return json_decode($lead->comment, true);
    }

    public function test_detailles_row_stores_feuille_and_extras_flat_in_the_comment(): void
    {
        $this->send($this->row('Detailles', 15, [
            'extra' => [
                'assure_actuellement' => 'oui',
                'raison_changement' => 'prix',
                'immatriculation' => 'AB-123-CD',
            ],
        ]))->assertOk()->assertJsonPath('data.status', 'created');

        $lead = Lead::sole();

        $this->assertSame('Detailles:15', $lead->sheet_row_key);
        $this->assertSame([
            'feuille' => 'Detailles',
            'assure_actuellement' => 'oui',
            'raison_changement' => 'prix',
            'immatriculation' => 'AB-123-CD',
        ], $this->comment($lead));
    }

    public function test_decennale_company_fields_end_up_in_the_comment(): void
    {
        $this->send($this->row('Decennale', 8, [
            'insurance_type' => 'Décennale',
            'extra' => [
                'statut' => 'Artisan',
                'forme_juridique' => 'SARL',
                'nombre_salaries' => '3',
                'nom_societe' => 'Dupont BTP',
            ],
        ]))->assertOk();

        $lead = Lead::sole();

        $this->assertSame('DECENNALE', $lead->insurance_type->value);
        $this->assertSame([
            'feuille' => 'Decennale',
            'statut' => 'Artisan',
            'forme_juridique' => 'SARL',
            'nombre_salaries' => '3',
            'nom_societe' => 'Dupont BTP',
        ], $this->comment($lead));
    }

    public function test_empty_extras_and_webhook_metadata_stay_out_of_the_comment(): void
    {
        $this->send($this->row('Lead', 4, [
            'rattrapage' => true,
            'doublon' => true,
            'extra' => ['assure_actuellement' => 'oui', 'immatriculation' => ''],
        ]))->assertOk();

        $comment = $this->comment(Lead::sole());

        $this->assertSame(['feuille' => 'Lead', 'assure_actuellement' => 'oui'], $comment);
        $this->assertArrayNotHasKey('extra', $comment);
        $this->assertArrayNotHasKey('rattrapage', $comment);
        $this->assertArrayNotHasKey('immatriculation', $comment);
    }

    public function test_old_payloads_with_extras_at_the_top_level_still_work(): void
    {
        $payload = $this->row('Detailles', 6, ['raison_changement' => 'prix']);
        unset($payload['extra']);

        $this->send($payload)->assertOk();

        $this->assertSame(['feuille' => 'Detailles', 'raison_changement' => 'prix'], $this->comment(Lead::sole()));
    }

    public function test_a_caught_up_row_is_stored_and_logged_with_its_lead(): void
    {
        Log::spy();

        $this->send($this->row('Detailles', 12, ['rattrapage' => true]))->assertOk();

        $lead = Lead::sole();

        Log::shouldHaveReceived('warning')
            ->with('google_sheets_webhook: lead manquant rattrape', Mockery::on(fn (array $context) => $context === [
                'sheet' => 'Detailles',
                'row' => '12',
                'status' => 'created',
                'lead_id' => $lead->id,
            ]))
            ->once();
    }

    public function test_a_normal_row_is_not_logged_as_caught_up(): void
    {
        Log::spy();

        $this->send($this->row('Detailles', 12))->assertOk();

        Log::shouldNotHaveReceived('warning');
    }

    public function test_resending_an_already_stored_row_does_not_create_a_second_lead(): void
    {
        $this->send($this->row('Detailles', 12))->assertJsonPath('data.status', 'created');

        $this->send($this->row('Detailles', 12, ['rattrapage' => true]))
            ->assertOk()
            ->assertJsonPath('data.status', 'updated');

        $this->assertSame(1, Lead::count());
    }

    /**
     * The whole catch-up as the Apps Script runs it: row 15 arrives while
     * the CRM's last stored row is 11, so it sends 12, 13, 14 (rattrapage)
     * then 15, and the CRM ends up with every row once.
     */
    public function test_catch_up_fills_the_gap_between_the_last_stored_row_and_the_new_one(): void
    {
        $this->send($this->row('Detailles', 11));
        $this->send($this->row('Lead', 40));

        $lastRow = $this->getJson('/api/v1/webhooks/google-sheets/last-row?sheet=Detailles', ['X-Webhook-Secret' => self::SECRET])
            ->json('data.last_row');
        $this->assertSame(11, $lastRow);

        foreach (range($lastRow + 1, 14) as $row) {
            $this->send($this->row('Detailles', $row, ['rattrapage' => true]))->assertJsonPath('data.status', 'created');
        }
        $this->send($this->row('Detailles', 15))->assertJsonPath('data.status', 'created');

        $this->assertSame(
            ['Detailles:11', 'Detailles:12', 'Detailles:13', 'Detailles:14', 'Detailles:15'],
            Lead::where('sheet_row_key', 'like', 'Detailles:%')->orderBy('id')->pluck('sheet_row_key')->all(),
        );
        $this->getJson('/api/v1/webhooks/google-sheets/last-row?sheet=Detailles', ['X-Webhook-Secret' => self::SECRET])
            ->assertJsonPath('data.last_row', 15);
        $this->getJson('/api/v1/webhooks/google-sheets/last-row?sheet=Lead', ['X-Webhook-Secret' => self::SECRET])
            ->assertJsonPath('data.last_row', 40);
    }

    public function test_a_row_without_phone_or_email_is_skipped_and_not_counted_as_stored(): void
    {
        $this->send($this->row('Lead', 3))->assertJsonPath('data.status', 'created');
        $this->send($this->row('Lead', 4, ['phone' => '', 'email' => '']))->assertJsonPath('data.status', 'skipped');

        $this->getJson('/api/v1/webhooks/google-sheets/last-row?sheet=Lead', ['X-Webhook-Secret' => self::SECRET])
            ->assertJsonPath('data.last_row', 3);
    }
}
