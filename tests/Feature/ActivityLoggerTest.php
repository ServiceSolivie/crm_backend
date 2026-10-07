<?php

namespace Tests\Feature;

use App\Enums\ActivityCategoryEnum;
use App\Enums\ActivityEventEnum;
use App\Services\ActivityLogger;
use Tests\TestCase;

/**
 * The activity journal is a side effect: it must never break the feature
 * that writes to it, and it must not be flooded by a repeated failure.
 */
class ActivityLoggerTest extends TestCase
{
    public function test_recording_never_throws_even_when_the_journal_cannot_be_written(): void
    {
        // No journal tables in this database: every write fails
        $logger = app(ActivityLogger::class);

        $logger->record(ActivityEventEnum::PAYMENT_PAID, fn () => [
            'key' => 'session:1',
            'category' => ActivityCategoryEnum::PAYMENT,
            'title' => 'Paiement PAY-1-1',
        ], '20,00 € reçus.', ['amount' => '20.00']);

        $logger->hyperswitchUnreachable('test');
        $logger->unknownAccount('nobody@example.com', ActivityEventEnum::AUTH_LOGIN_FAILED, 'Adresse e-mail inconnue.');

        $this->assertTrue(true, 'The caller went on');
    }

    public function test_a_broken_operation_description_does_not_break_the_caller_either(): void
    {
        app(ActivityLogger::class)->record(ActivityEventEnum::PAYMENT_PAID, fn () => throw new \RuntimeException('no session'));

        $this->assertTrue(true);
    }

    public function test_a_repeated_problem_is_let_through_once_per_period(): void
    {
        $logger = app(ActivityLogger::class);

        $this->assertTrue($logger->allowed('login-failed|agent@crm.test|10.0.0.1', 60));
        $this->assertFalse($logger->allowed('login-failed|agent@crm.test|10.0.0.1', 60));
        $this->assertFalse($logger->allowed('login-failed|agent@crm.test|10.0.0.1', 60));

        // Another address, or another account, is its own count
        $this->assertTrue($logger->allowed('login-failed|agent@crm.test|10.0.0.2', 60));
        $this->assertTrue($logger->allowed('login-failed|other@crm.test|10.0.0.1', 60));
    }
}
