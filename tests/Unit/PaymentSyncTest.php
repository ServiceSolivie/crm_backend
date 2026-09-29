<?php

namespace Tests\Unit;

use App\Enums\PaymentRecordStatusEnum;
use App\Enums\PaymentSessionStatusEnum;
use App\Support\PaymentResultPage;
use App\Support\PaymentSyncSchedule;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Phase 2 rules without Hyperswitch nor database: status mapping, the
 * follow-up schedule (force_sync never in a tight loop) and the result
 * page token / lifetime.
 */
class PaymentSyncTest extends TestCase
{
    public function test_hyperswitch_statuses_map_to_session_statuses(): void
    {
        $this->assertSame(PaymentSessionStatusEnum::PAYEE, PaymentSessionStatusEnum::fromProvider('succeeded'));
        $this->assertSame(PaymentSessionStatusEnum::ECHOUEE, PaymentSessionStatusEnum::fromProvider('failed'));
        $this->assertSame(PaymentSessionStatusEnum::ANNULEE, PaymentSessionStatusEnum::fromProvider('cancelled'));
        $this->assertSame(PaymentSessionStatusEnum::EXPIREE, PaymentSessionStatusEnum::fromProvider('expired'));
        // Not final: the session stays as it is
        $this->assertNull(PaymentSessionStatusEnum::fromProvider('requires_customer_action'));
        $this->assertNull(PaymentSessionStatusEnum::fromProvider('processing'));
    }

    public function test_only_succeeded_means_paid_and_pending_is_processing(): void
    {
        // Doc §7: requires_capture / authorized / partially_captured are not paid
        foreach (['partially_captured', 'requires_capture', 'authorized'] as $status) {
            $this->assertNull(PaymentSessionStatusEnum::fromProvider($status), $status);
            $this->assertNotSame(PaymentRecordStatusEnum::REUSSI, PaymentRecordStatusEnum::fromProvider($status), $status);
        }
        $this->assertSame(PaymentRecordStatusEnum::EN_ATTENTE, PaymentRecordStatusEnum::fromProvider('pending'));
        $this->assertTrue(PaymentSessionStatusEnum::providerIsProcessing('pending'));
        $this->assertTrue(PaymentSessionStatusEnum::providerIsProcessing('processing'));
        $this->assertFalse(PaymentSessionStatusEnum::providerIsProcessing('requires_customer_action'));
    }

    public function test_hyperswitch_statuses_map_to_payment_statuses(): void
    {
        $this->assertSame(PaymentRecordStatusEnum::REUSSI, PaymentRecordStatusEnum::fromProvider('succeeded'));
        $this->assertSame(PaymentRecordStatusEnum::EN_ATTENTE, PaymentRecordStatusEnum::fromProvider('processing'));
        $this->assertSame(PaymentRecordStatusEnum::ECHOUE, PaymentRecordStatusEnum::fromProvider('failed'));
        $this->assertSame(PaymentRecordStatusEnum::ANNULE, PaymentRecordStatusEnum::fromProvider('cancelled'));
        // The client hasn't paid: nothing to record
        $this->assertNull(PaymentRecordStatusEnum::fromProvider('requires_customer_action'));
        $this->assertNull(PaymentRecordStatusEnum::fromProvider('expired'));
    }

    public function test_only_open_and_to_check_sessions_are_pending(): void
    {
        $this->assertFalse(PaymentSessionStatusEnum::OUVERTE->isFinal());
        $this->assertFalse(PaymentSessionStatusEnum::A_VERIFIER->isFinal());
        foreach ([PaymentSessionStatusEnum::PAYEE, PaymentSessionStatusEnum::ECHOUEE, PaymentSessionStatusEnum::ANNULEE, PaymentSessionStatusEnum::EXPIREE] as $status) {
            $this->assertTrue($status->isFinal(), $status->value);
        }
    }

    public function test_only_one_forced_sync_30_seconds_after_the_return(): void
    {
        $returned = Carbon::parse('2026-09-28 10:00:00');

        $this->assertSame('10:00:30', PaymentSyncSchedule::nextForcedAt($returned, 0)->format('H:i:s'));
        $this->assertNull(PaymentSyncSchedule::nextForcedAt($returned, 1), 'no second forced sync');
    }

    public function test_normal_reads_every_15_seconds_during_the_first_10_minutes_only(): void
    {
        $created = Carbon::parse('2026-09-28 10:00');

        $this->assertSame(15, PaymentSyncSchedule::firstNormalDelay());
        $this->assertSame(15, PaymentSyncSchedule::nextNormalDelay($created, $created->copy()->addSeconds(15)));
        $this->assertSame(15, PaymentSyncSchedule::nextNormalDelay($created, $created->copy()->addSeconds(599)));
        $this->assertNull(PaymentSyncSchedule::nextNormalDelay($created, $created->copy()->addMinutes(10)));
    }

    public function test_result_page_token_is_random_and_only_its_hash_is_stored(): void
    {
        $a = PaymentResultPage::generateToken();
        $b = PaymentResultPage::generateToken();

        $this->assertSame(48, strlen($a));
        $this->assertNotSame($a, $b);
        $this->assertSame(hash('sha256', $a), PaymentResultPage::hash($a));
        $this->assertNotSame($a, PaymentResultPage::hash($a));
        $this->assertSame('https://crm.test/paiement/'.$a, PaymentResultPage::url('https://crm.test/', $a));
    }

    public function test_result_page_expires_two_hours_after_the_client_came_back(): void
    {
        $returned = Carbon::parse('2026-09-28 10:00');

        // Not come back yet: usable (the client may pay days later)
        $this->assertFalse(PaymentResultPage::isExpired(null, $returned->copy()->addDays(5), 120));
        $this->assertFalse(PaymentResultPage::isExpired($returned, $returned->copy()->addMinutes(119), 120));
        $this->assertTrue(PaymentResultPage::isExpired($returned, $returned->copy()->addMinutes(120), 120));
    }
}
