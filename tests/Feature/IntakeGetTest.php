<?php

namespace Tests\Feature;

use App\Models\IntakeRequest;
use Tests\TestCase;

class IntakeGetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Only these tables: the full migrations are MySQL-only
        $this->artisan('migrate', ['--path' => ['database/migrations/2026_10_13_110000_create_trusted_devices_table.php', 'database/migrations/2026_10_24_100000_create_intake_requests_table.php']]);
    }

    public function test_a_get_without_a_key_is_recorded_with_its_data(): void
    {
        $this->get('/api/v1/intake/test?first_name=Jean&phone=0612345678')->assertOk()->assertJson(['received' => true]);

        $recorded = IntakeRequest::first();
        $this->assertSame('GET', $recorded->method);
        $this->assertSame(['first_name' => 'Jean', 'phone' => '0612345678'], $recorded->query);
    }

    public function test_only_get_is_accepted(): void
    {
        $this->postJson('/api/v1/intake/test', ['first_name' => 'Jean'])->assertMethodNotAllowed();

        $this->assertSame(0, IntakeRequest::count());
    }

    public function test_the_leads_address_still_needs_its_key(): void
    {
        $this->get('/api/v1/intake/leads?first_name=Jean')->assertUnauthorized();

        $this->assertNull(IntakeRequest::first()->query);
    }
}
