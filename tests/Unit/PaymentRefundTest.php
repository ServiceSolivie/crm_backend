<?php

namespace Tests\Unit;

use App\Enums\PaymentRefundStatusEnum;
use App\Enums\PaymentSessionStatusEnum;
use App\Services\HyperswitchClient;
use App\Support\RefundSyncSchedule;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use PHPUnit\Framework\TestCase;

/**
 * Refund rules without Hyperswitch nor database: status mapping, the
 * follow-up schedule (a pending credit is never checked in a tight loop),
 * the CRM-chosen refund id and the refund body (always the full amount).
 */
class PaymentRefundTest extends TestCase
{
    public function test_hyperswitch_refund_statuses_map_to_refund_statuses(): void
    {
        $this->assertSame(PaymentRefundStatusEnum::REUSSI, PaymentRefundStatusEnum::fromProvider('succeeded'));
        $this->assertSame(PaymentRefundStatusEnum::EN_ATTENTE, PaymentRefundStatusEnum::fromProvider('pending'));
        $this->assertSame(PaymentRefundStatusEnum::EN_ATTENTE, PaymentRefundStatusEnum::fromProvider('manual_review'));
        $this->assertSame(PaymentRefundStatusEnum::ECHOUE, PaymentRefundStatusEnum::fromProvider('failed'));
        $this->assertSame(PaymentRefundStatusEnum::ECHOUE, PaymentRefundStatusEnum::fromProvider('transaction_failure'));
        // Unknown value: keep the current status
        $this->assertNull(PaymentRefundStatusEnum::fromProvider('something_new'));
        $this->assertNull(PaymentRefundStatusEnum::fromProvider(null));
    }

    public function test_only_checking_and_waiting_refunds_are_pending(): void
    {
        $this->assertFalse(PaymentRefundStatusEnum::A_VERIFIER->isFinal());
        $this->assertFalse(PaymentRefundStatusEnum::EN_ATTENTE->isFinal());
        $this->assertTrue(PaymentRefundStatusEnum::REUSSI->isFinal());
        $this->assertTrue(PaymentRefundStatusEnum::ECHOUE->isFinal());
    }

    public function test_a_failed_refund_does_not_block_a_new_one(): void
    {
        $blocking = PaymentRefundStatusEnum::blocking();

        $this->assertContains(PaymentRefundStatusEnum::A_VERIFIER, $blocking);
        $this->assertContains(PaymentRefundStatusEnum::EN_ATTENTE, $blocking);
        $this->assertContains(PaymentRefundStatusEnum::REUSSI, $blocking);
        $this->assertNotContains(PaymentRefundStatusEnum::ECHOUE, $blocking);
    }

    public function test_a_refunded_session_is_final(): void
    {
        $this->assertTrue(PaymentSessionStatusEnum::REMBOURSEE->isFinal());
        $this->assertSame('Remboursée', PaymentSessionStatusEnum::REMBOURSEE->label());
    }

    public function test_unclear_refund_is_checked_every_minute_a_few_times(): void
    {
        $now = Carbon::parse('2026-10-01 10:00:00');

        $next = RefundSyncSchedule::nextCheckAt(PaymentRefundStatusEnum::A_VERIFIER, 1, $now, $now);
        $this->assertEquals($now->copy()->addMinute(), $next);

        $this->assertNull(RefundSyncSchedule::nextCheckAt(PaymentRefundStatusEnum::A_VERIFIER, RefundSyncSchedule::CHECK_MAX_ATTEMPTS, $now, $now));
        $this->assertFalse(RefundSyncSchedule::shouldForce(PaymentRefundStatusEnum::A_VERIFIER));
    }

    public function test_waiting_credit_is_forced_with_growing_delays_then_stops(): void
    {
        $requestedAt = Carbon::parse('2026-10-01 10:00:00');
        $next = fn (CarbonInterface $now) => RefundSyncSchedule::nextCheckAt(PaymentRefundStatusEnum::EN_ATTENTE, 1, $requestedAt, $now);

        $this->assertTrue(RefundSyncSchedule::shouldForce(PaymentRefundStatusEnum::EN_ATTENTE));

        // First check one minute after the request (the answer to POST /refunds comes a few seconds in)
        $this->assertEquals($requestedAt->copy()->addMinute(), $next($requestedAt->copy()->addSeconds(4)));

        // Then the delay grows with the age of the refund: [age in minutes, next check in minutes]
        foreach ([
            [1, 30],        // 0 – 2 h: every 30 min
            [119, 30],
            [120, 120],     // 2 – 6 h: every 2 h
            [359, 120],
            [360, 360],     // 6 – 24 h: every 6 h
            [1439, 360],
            [1440, 720],    // 24 – 72 h: every 12 h
            [4319, 720],
            [4320, 1440],   // 3 – 7 days: every 24 h
            [10079, 1440],
        ] as [$ageMinutes, $everyMinutes]) {
            $now = $requestedAt->copy()->addMinutes($ageMinutes);
            $this->assertEquals($now->copy()->addMinutes($everyMinutes), $next($now), "age {$ageMinutes} min");
        }

        // After 7 days the automatic checks stop
        $this->assertNull($next($requestedAt->copy()->addDays(7)));
        $this->assertNull($next($requestedAt->copy()->addDays(11)));
    }

    public function test_waiting_credit_follows_the_whole_plan_in_eighteen_checks(): void
    {
        $requestedAt = Carbon::parse('2026-10-01 10:00:00');
        $at = $requestedAt->copy()->addSeconds(4); // answer to POST /refunds: pending
        $checks = [];

        while ($at = RefundSyncSchedule::nextCheckAt(PaymentRefundStatusEnum::EN_ATTENTE, count($checks) + 1, $requestedAt, $at)) {
            $checks[] = $at;
        }

        $this->assertCount(18, $checks);
        $this->assertEquals($requestedAt->copy()->addMinute(), $checks[0]);
        // The last automatic check comes a week after the request, a day at most
        $lastAge = $requestedAt->diffInHours(end($checks), true);
        $this->assertGreaterThanOrEqual(7 * 24, $lastAge);
        $this->assertLessThanOrEqual(8 * 24, $lastAge);
    }

    public function test_final_refunds_are_not_rescheduled(): void
    {
        $now = Carbon::parse('2026-10-01 10:00:00');

        $this->assertNull(RefundSyncSchedule::nextCheckAt(PaymentRefundStatusEnum::REUSSI, 1, $now, $now));
        $this->assertNull(RefundSyncSchedule::nextCheckAt(PaymentRefundStatusEnum::ECHOUE, 1, $now, $now));
    }

    public function test_refund_id_has_the_30_characters_hyperswitch_expects(): void
    {
        $id = HyperswitchClient::generateRefundId();

        $this->assertSame(30, strlen($id));
        $this->assertStringStartsWith('ref_', $id);
        $this->assertNotSame($id, HyperswitchClient::generateRefundId());
    }

    public function test_refund_body_is_the_full_amount_in_cents(): void
    {
        $client = new HyperswitchClient('https://hs.test', 'snd_test', 'pro_test', 'sogecommerce', 'https://crm.test');

        $payload = $client->buildRefundPayload('ref_x', 'pay_y', '99.90', 'Demande du client', ['crm_reference' => 'PAY-1-1']);

        $this->assertSame([
            'payment_id' => 'pay_y',
            'refund_id' => 'ref_x',
            'amount' => 9990,
            'reason' => 'Demande du client',
            'metadata' => ['source' => 'solva-crm', 'crm_reference' => 'PAY-1-1'],
        ], $payload);
    }
}
