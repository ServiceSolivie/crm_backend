<?php

namespace Tests\Unit;

use App\Enums\ActivityCategoryEnum;
use App\Enums\ActivityEventEnum;
use App\Enums\ActivityLevelEnum;
use App\Enums\ActivityStateEnum;
use App\Models\ActivityLog;
use App\Models\Lead;
use App\Models\PaymentSession;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * Activity journal rules without database: every event is fully described,
 * a refund belongs to its payment's operation, and nothing secret is kept.
 */
class ActivityLogTest extends TestCase
{
    public function test_every_event_has_a_category_a_level_and_a_label(): void
    {
        foreach (ActivityEventEnum::cases() as $event) {
            $this->assertInstanceOf(ActivityCategoryEnum::class, $event->category(), $event->value);
            $this->assertInstanceOf(ActivityLevelEnum::class, $event->level(), $event->value);
            $this->assertNotSame('', trim($event->label()), $event->value);

            // The event code starts with its category: "refund.failed" is a refund
            $this->assertStringStartsWith($event->category()->value.'.', $event->value);
        }
    }

    public function test_an_event_that_changes_the_state_of_its_operation_names_that_state(): void
    {
        foreach (ActivityEventEnum::cases() as $event) {
            if ($event->state() === null) {
                $this->assertNull($event->stateLabel(), $event->value);
            } else {
                $this->assertNotSame('', trim((string) $event->stateLabel()), $event->value);
            }
        }
    }

    public function test_failures_and_warnings_never_leave_an_operation_looking_fine(): void
    {
        foreach (ActivityEventEnum::cases() as $event) {
            if ($event->level() === ActivityLevelEnum::FAILURE && $event->state() !== null) {
                $this->assertSame(ActivityStateEnum::FAILURE, $event->state(), $event->value);
            }
            if ($event->level()->isProblem() && $event->state() !== null) {
                $this->assertTrue($event->state()->isProblem(), $event->value);
            }
        }
    }

    public function test_the_main_events_leave_the_expected_state(): void
    {
        $this->assertSame(ActivityStateEnum::PENDING, ActivityEventEnum::PAYMENT_LINK_CREATED->state());
        $this->assertSame(ActivityStateEnum::SUCCESS, ActivityEventEnum::PAYMENT_PAID->state());
        $this->assertSame(ActivityStateEnum::FAILURE, ActivityEventEnum::PAYMENT_FAILED->state());
        $this->assertSame(ActivityStateEnum::WARNING, ActivityEventEnum::PAYMENT_LINK_UNCONFIRMED->state());
        $this->assertSame(ActivityStateEnum::PENDING, ActivityEventEnum::REFUND_PENDING->state());
        $this->assertSame(ActivityStateEnum::SUCCESS, ActivityEventEnum::REFUND_SUCCEEDED->state());
        $this->assertSame(ActivityStateEnum::WARNING, ActivityEventEnum::REFUND_FOLLOW_UP_STOPPED->state());
        $this->assertSame(ActivityStateEnum::FAILURE, ActivityEventEnum::GOOGLE_ADS_REJECTED->state());

        // They are logged, but the operation stands as it did
        $this->assertNull(ActivityEventEnum::PAYMENT_CLIENT_RETURNED->state());
        $this->assertNull(ActivityEventEnum::PAYMENT_LINK_EMAILED->state());
        $this->assertNull(ActivityEventEnum::REFUND_BLOCKED->state());
        $this->assertNull(ActivityEventEnum::GOOGLE_ADS_DUPLICATE_IGNORED->state());
    }

    public function test_refund_events_are_refunds_logged_inside_a_payment_operation(): void
    {
        foreach (ActivityEventEnum::cases() as $event) {
            if (str_starts_with($event->value, 'refund.')) {
                $this->assertSame(ActivityCategoryEnum::REFUND, $event->category());
            }
        }

        $session = new PaymentSession(['reference' => 'PAY-33509-5', 'amount' => '20.00', 'lead_id' => 33509]);
        $session->id = 124;
        $session->setRelation('lead', new Lead(['first_name' => 'Mohamed', 'last_name' => 'Test', 'reference' => 'LD-1']));

        $operation = ActivityLogger::sessionOperation($session);

        // The link and its refund share this key: one operation, one story
        $this->assertSame('session:124', $operation['key']);
        $this->assertSame(ActivityCategoryEnum::PAYMENT, $operation['category']);
        $this->assertSame('Paiement PAY-33509-5', $operation['title']);
        $this->assertSame('Mohamed Test · 20,00 €', $operation['subtitle']);
        $this->assertSame('PAY-33509-5', $operation['reference']);
        $this->assertSame(33509, $operation['lead_id']);
    }

    public function test_secrets_are_dropped_from_the_details_at_any_depth(): void
    {
        $clean = ActivityLogger::withoutSecrets([
            'amount' => '20.00',
            'google_key' => 'the-shared-key',
            'password' => 'hunter2',
            'client_secret' => 'pay_x_secret_y',
            'API_KEY' => 'snd_live',
            'nested' => ['token' => 'abc', 'status' => 'succeeded'],
            'empty' => null,
            'blank' => '',
        ]);

        $this->assertSame(['amount' => '20.00', 'nested' => ['status' => 'succeeded']], $clean);
    }

    public function test_an_address_is_only_kept_for_whoever_did_the_action(): void
    {
        $request = Request::create('/api/v1/leads/1/payment-sessions', 'POST', server: [
            'REMOTE_ADDR' => '196.74.10.20',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/140',
        ]);

        // A user, the client and Google Ads are the caller of their request
        foreach ([ActivityLog::ACTOR_USER, ActivityLog::ACTOR_CLIENT, ActivityLog::ACTOR_GOOGLE_ADS] as $actor) {
            $this->assertSame(['196.74.10.20', 'Mozilla/5.0 Chrome/140'], ActivityLogger::originOf($actor, $request), $actor);
        }

        // A step of the system (the link e-mail sent inside the agent's request) is nobody's address
        $this->assertSame([null, null], ActivityLogger::originOf(ActivityLog::ACTOR_SYSTEM, $request));

        // The scheduled job has no request at all
        $this->assertSame([null, null], ActivityLogger::originOf(ActivityLog::ACTOR_USER, null));
    }

    public function test_amounts_are_shown_the_french_way(): void
    {
        $this->assertSame('20,00 €', ActivityLogger::euros('20'));
        $this->assertSame('1 234,50 €', ActivityLogger::euros(1234.5));
    }
}
