<?php

namespace Tests\Feature;

use App\Models\Lead;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The endpoint the Apps Script calls before sending a new row: the highest
 * row already stored for that sheet, so every row after it gets resent.
 *
 * The project's migrations are MySQL-only, so this builds just the leads
 * columns the lookup needs on the in-memory test database.
 */
class GoogleSheetsLastRowTest extends TestCase
{
    private const SECRET = 'test-secret';

    private int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google.webhook_secret' => self::SECRET]);

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('reference');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone');
            $table->string('insurance_type');
            $table->string('dvc_status')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->string('sheet_row_key')->nullable();
            $table->boolean('is_doublon')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function lead(string $sheetRowKey, array $attributes = []): Lead
    {
        $this->n++;

        return Lead::forceCreate(array_merge([
            'reference' => "TEST-{$this->n}",
            'first_name' => 'Test',
            'last_name' => 'Lead',
            'phone' => "060000000{$this->n}",
            'insurance_type' => 'AUTO',
            'created_by' => 1,
            'sheet_row_key' => $sheetRowKey,
        ], $attributes));
    }

    private function lastRow(string $sheet)
    {
        return $this->getJson(
            '/api/v1/webhooks/google-sheets/last-row?sheet='.urlencode($sheet),
            ['X-Webhook-Secret' => self::SECRET],
        );
    }

    public function test_returns_the_highest_row_of_the_sheet_numerically(): void
    {
        $this->lead('Detailles:9');
        $this->lead('Detailles:12');
        $this->lead('Detailles:100');

        $this->lastRow('Detailles')->assertOk()->assertJsonPath('data.last_row', 100);
    }

    public function test_other_sheets_with_the_same_row_numbers_are_ignored(): void
    {
        $this->lead('Detailles:14');
        $this->lead('Lead:50');
        $this->lead('Decennale:80');

        $this->lastRow('Detailles')->assertJsonPath('data.last_row', 14);
        $this->lastRow('Lead')->assertJsonPath('data.last_row', 50);
    }

    public function test_doublons_and_deleted_leads_count_as_stored(): void
    {
        $this->lead('Lead:7');
        $this->lead('Lead:8', ['is_doublon' => true]);
        $this->lead('Lead:9')->delete();

        $this->lastRow('Lead')->assertJsonPath('data.last_row', 9);
    }

    public function test_returns_null_when_nothing_is_stored_for_the_sheet(): void
    {
        $this->lead('Lead:5');

        $this->lastRow('Decennale')
            ->assertOk()
            ->assertJsonPath('data.sheet', 'Decennale')
            ->assertJsonPath('data.last_row', null);
    }

    public function test_requires_the_sheet_parameter(): void
    {
        $this->getJson('/api/v1/webhooks/google-sheets/last-row', ['X-Webhook-Secret' => self::SECRET])
            ->assertStatus(422);
    }

    public function test_rejects_a_wrong_secret(): void
    {
        $this->getJson('/api/v1/webhooks/google-sheets/last-row?sheet=Lead', ['X-Webhook-Secret' => 'wrong'])
            ->assertStatus(401);
    }
}
