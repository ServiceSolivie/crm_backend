<?php

namespace Tests\Feature;

use App\Models\IntakeRequest;
use Tests\TestCase;

class IntakeFacebookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Only these tables: the full migrations are MySQL-only
        $this->artisan('migrate', ['--path' => ['database/migrations/2026_10_13_110000_create_trusted_devices_table.php', 'database/migrations/2026_10_24_100000_create_intake_requests_table.php']]);
    }

    public function test_it_answers_the_facebook_check_without_a_key(): void
    {
        $response = $this->get('/api/v1/intake/facebook?hub.mode=subscribe&hub.challenge=1158201444&hub.verify_token=anything');

        $response->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $this->assertSame('1158201444', $response->getContent());
    }

    public function test_a_plain_get_answers_and_is_recorded(): void
    {
        $this->get('/api/v1/intake/facebook')->assertOk()->assertJson(['received' => true]);

        $this->assertSame('GET', IntakeRequest::first()->method);
    }

    public function test_it_records_what_facebook_posts(): void
    {
        $payload = ['object' => 'page', 'entry' => [['changes' => [['field' => 'leadgen', 'value' => ['leadgen_id' => '444']]]]]];

        $this->postJson('/api/v1/intake/facebook', $payload)->assertOk()->assertJson(['received' => true]);

        $recorded = IntakeRequest::first();
        $this->assertStringContainsString('leadgen_id', $recorded->body);
        $this->assertSame('none', $recorded->key_via);
    }

    public function test_the_leads_address_still_needs_its_key(): void
    {
        $this->get('/api/v1/intake/leads?hub.mode=subscribe&hub.challenge=1')->assertUnauthorized();
    }
}
