<?php

namespace Tests\Unit;

use App\Enums\CallDirectionEnum;
use App\Enums\CallStatusEnum;
use App\Services\Ringover\RingoverCallMapper;
use Tests\TestCase;

class RingoverCallMapperTest extends TestCase
{
    protected RingoverCallMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new RingoverCallMapper;
    }

    public function test_it_maps_a_hangup_event(): void
    {
        $data = $this->mapper->fromWebhook('hangup', [
            'call_id' => '12345678901234567890',
            'channel_id' => 'ch-1',
            'direction' => 'out',
            'from_number' => 33184800001,
            'to_number' => 33612345678,
            'user_id' => 900001,
            'start_time' => 1791200000,
            'answered_time' => 1791200010,
            'hangup_time' => 1791200250,
            'duration_in_seconds' => 250,
            'record' => 'https://cdn.ringover.com/records/a.mp3',
        ]);

        $this->assertSame('12345678901234567890', $data['ringover_call_id']);
        $this->assertSame(CallDirectionEnum::OUT, $data['direction']);
        $this->assertSame('+33184800001', $data['from_number']);
        $this->assertSame('+33612345678', $data['to_number']);
        $this->assertSame('900001', $data['ringover_user_id']);
        $this->assertTrue($data['ended']);
        $this->assertSame(250, $data['duration_seconds']);
        $this->assertSame(1791200010, $data['answered_at']->timestamp);
        $this->assertSame('https://cdn.ringover.com/records/a.mp3', $data['recording_url']);
    }

    public function test_after_call_events_carry_only_their_data(): void
    {
        $summary = $this->mapper->fromWebhook('summary_available', ['call_id' => '42', 'summary' => 'Client intéressé.']);
        $this->assertSame(['ringover_call_id' => '42', 'ringover_channel_id' => null, 'ai_summary' => 'Client intéressé.'], $summary);

        $voicemail = $this->mapper->fromWebhook('voicemail_available', ['call_id' => '42', 'voicemail_link' => 'https://vm']);
        $this->assertSame(CallStatusEnum::VOICEMAIL, $voicemail['status']);
        $this->assertSame('https://vm', $voicemail['voicemail_url']);
    }

    public function test_it_ignores_unhandled_events_and_payloads_without_call_id(): void
    {
        $this->assertNull($this->mapper->fromWebhook('tags_updated', ['call_id' => '42']));
        $this->assertNull($this->mapper->fromWebhook('ringing', ['direction' => 'in']));
    }

    public function test_it_maps_the_calls_api(): void
    {
        $answered = $this->mapper->fromCallsApi([
            'call_id' => '99', 'direction' => 'OUT', 'last_state' => 'ANSWERED',
            'start_time' => '2026-10-05T10:00:00.000Z', 'answered_time' => '2026-10-05T10:00:08.000Z',
            'end_time' => '2026-10-05T10:02:00.000Z', 'incall_duration' => 112, 'total_duration' => 120,
            'from_number' => 33184800001, 'to_number' => 33612345678, 'user' => ['user_id' => 900001],
        ]);

        $this->assertSame(CallStatusEnum::COMPLETED, $answered['status']);
        $this->assertSame(112, $answered['talk_seconds']);
        $this->assertSame('900001', $answered['ringover_user_id']);

        $missed = $this->mapper->fromCallsApi(['call_id' => '100', 'direction' => 'IN', 'last_state' => 'MISSED']);
        $this->assertArrayNotHasKey('status', $missed);
        $this->assertTrue($missed['ended']);

        $voicemail = $this->mapper->fromCallsApi(['call_id' => '101', 'direction' => 'IN', 'last_state' => 'VOICEMAIL']);
        $this->assertSame(CallStatusEnum::VOICEMAIL, $voicemail['status']);
    }
}
