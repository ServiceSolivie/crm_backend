<?php

namespace Tests\Feature;

use App\Exceptions\ApiException;
use App\Exceptions\HyperswitchUncertainException;
use App\Services\HyperswitchClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * How the client reads Hyperswitch's answers (HTTP faked, no database):
 * a clear refusal means nothing was created, anything unclear means the
 * payment may exist and must be checked, never created again.
 */
class HyperswitchClientHttpTest extends TestCase
{
    private const PAYMENT_ID = 'pay_abcdefghijklmnopqrstuvwxyz';

    private function client(): HyperswitchClient
    {
        return new HyperswitchClient('https://hs.test', 'snd_test', 'pro_test', 'sogecommerce', 'https://crm.test/retour', 5);
    }

    private function create(): array
    {
        return $this->client()->createRedirectPayment(self::PAYMENT_ID, '9.90', 'EUR', 'client@example.com', 'PAY-1-1', 'lead-1');
    }

    public function test_success_returns_the_link_and_sends_the_crm_payment_id(): void
    {
        Http::fake(['hs.test/payments' => Http::response([
            'payment_id' => self::PAYMENT_ID,
            'status' => 'requires_customer_action',
            'client_secret' => 'secret',
            'next_action' => ['redirect_to_url' => 'https://hs.test/redirect/1'],
        ])]);

        $result = $this->create();

        $this->assertSame(self::PAYMENT_ID, $result['payment_id']);
        $this->assertSame('https://hs.test/redirect/1', $result['payment_url']);
        $this->assertArrayNotHasKey('client_secret', $result['payload']);
        Http::assertSent(fn ($request) => $request['payment_id'] === self::PAYMENT_ID
            && $request['customer'] === ['id' => 'lead-1', 'email' => 'client@example.com']
            && ! isset($request['card']));
    }

    public function test_timeout_is_uncertain(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));

        $this->expectException(HyperswitchUncertainException::class);
        $this->create();
    }

    public function test_server_error_is_uncertain(): void
    {
        Http::fake(['hs.test/payments' => Http::response(['error' => ['message' => 'boom']], 500)]);

        $this->expectException(HyperswitchUncertainException::class);
        $this->create();
    }

    public function test_accepted_without_link_is_uncertain(): void
    {
        Http::fake(['hs.test/payments' => Http::response(['payment_id' => self::PAYMENT_ID, 'status' => 'processing'])]);

        $this->expectException(HyperswitchUncertainException::class);
        $this->create();
    }

    public function test_clear_refusal_is_not_uncertain(): void
    {
        Http::fake(['hs.test/payments' => Http::response(['error' => ['code' => 'HE_02', 'message' => 'Business profile does not exist']], 400)]);

        try {
            $this->create();
            $this->fail('An exception was expected');
        } catch (ApiException $e) {
            $this->assertNotInstanceOf(HyperswitchUncertainException::class, $e);
            $this->assertStringContainsString('Business profile does not exist', $e->getMessage());
        }
    }

    public function test_retrieve_is_normal_by_default_and_forced_only_when_asked(): void
    {
        Http::fake(['hs.test/payments/*' => Http::response(['payment_id' => self::PAYMENT_ID, 'status' => 'succeeded', 'client_secret' => 'x'])]);

        $normal = $this->client()->retrievePayment(self::PAYMENT_ID);
        $this->client()->retrievePayment(self::PAYMENT_ID, forceSync: true);

        $this->assertSame('succeeded', $normal['status']);
        $this->assertArrayNotHasKey('client_secret', $normal);
        $urls = Http::recorded()->map(fn ($pair) => $pair[0]->url())->all();
        $this->assertStringNotContainsString('force_sync', $urls[0]);
        $this->assertStringContainsString('force_sync=true', $urls[1]);
    }

    public function test_return_url_is_the_result_page_of_the_payment(): void
    {
        Http::fake(['hs.test/payments' => Http::response(['payment_id' => self::PAYMENT_ID, 'status' => 'requires_customer_action', 'next_action' => ['redirect_to_url' => 'https://hs.test/r']])]);

        $client = $this->client();
        $client->createRedirectPayment(self::PAYMENT_ID, '9.90', 'EUR', 'client@example.com', 'PAY-1-1', 'lead-1', [], $client->resultPageUrl('TOKEN123'));

        Http::assertSent(fn ($request) => $request['return_url'] === 'https://crm.test/retour/paiement/TOKEN123');
    }

    public function test_created_but_refused_by_the_connector_is_a_failed_payment_not_uncertain(): void
    {
        Http::fake(['hs.test/payments' => Http::response([
            'payment_id' => self::PAYMENT_ID, 'status' => 'failed', 'connector' => 'sogecommerce',
            'merchant_connector_id' => 'mca_1', 'error_code' => '51', 'error_message' => 'Provision insuffisante',
        ])]);

        $result = $this->create();

        $this->assertSame('failed', $result['status']);
        $this->assertNull($result['payment_url']);
        $this->assertSame('51', $result['error_code']);
        $this->assertSame('sogecommerce', $result['connector']);
        $this->assertSame('mca_1', $result['merchant_connector_id']);
    }

    public function test_logs_never_contain_the_client_email_nor_the_api_key(): void
    {
        Log::spy();
        Http::fake(['hs.test/payments' => Http::response([
            'payment_id' => self::PAYMENT_ID, 'status' => 'requires_customer_action',
            'email' => 'client@example.com', 'customer' => ['email' => 'client@example.com'],
            'next_action' => ['redirect_to_url' => 'https://hs.test/r'],
        ])]);

        $this->create();

        Log::shouldHaveReceived('info')->withArgs(function ($message, $context) {
            $dump = json_encode($context);

            return str_starts_with($message, 'hyperswitch: POST /payments')
                && ! str_contains($dump, 'client@example.com')
                && ! str_contains($dump, 'snd_test')
                && $context['payment_id'] === self::PAYMENT_ID
                && isset($context['http_status'], $context['duration_ms']);
        })->once();
    }

    /* ── Refunds ─────────────────────────────────────────────────────── */

    private const REFUND_ID = 'ref_abcdefghijklmnopqrstuvwxyz';

    private function refund(): array
    {
        return $this->client()->createRefund(self::REFUND_ID, self::PAYMENT_ID, '9.90', 'Demande du client', ['crm_reference' => 'PAY-1-1']);
    }

    public function test_refund_cancelled_before_settlement_is_succeeded_at_once(): void
    {
        Http::fake(['hs.test/refunds' => Http::response([
            'refund_id' => self::REFUND_ID,
            'payment_id' => self::PAYMENT_ID,
            'status' => 'succeeded',
            'amount' => 990,
            'connector_refund_id' => 'UUID_DU_DEBIT_INITIAL',
        ])]);

        $result = $this->refund();

        $this->assertSame('succeeded', $result['status']);
        $this->assertSame('UUID_DU_DEBIT_INITIAL', $result['connector_refund_id']);
        // Our own refund id and the full amount in cents are sent
        Http::assertSent(fn ($request) => $request->url() === 'https://hs.test/refunds'
            && $request['refund_id'] === self::REFUND_ID
            && $request['payment_id'] === self::PAYMENT_ID
            && $request['amount'] === 990);
    }

    public function test_refund_after_settlement_is_pending_with_a_new_credit(): void
    {
        Http::fake(['hs.test/refunds' => Http::response([
            'refund_id' => self::REFUND_ID,
            'status' => 'pending',
            'connector_refund_id' => 'NOUVEL_UUID_DE_CREDIT',
        ])]);

        $result = $this->refund();

        $this->assertSame('pending', $result['status']);
        $this->assertSame('NOUVEL_UUID_DE_CREDIT', $result['connector_refund_id']);
    }

    public function test_partial_refund_refusal_is_clear_not_uncertain(): void
    {
        Http::fake(['hs.test/refunds' => Http::response(['error' => [
            'type' => 'invalid_request',
            'code' => 'IR_19',
            'message' => 'Payment method type not supported',
            'reason' => 'Partial refund is not supported by sogecommerce',
        ]], 400)]);

        try {
            $this->refund();
            $this->fail('An exception was expected');
        } catch (ApiException $e) {
            $this->assertNotInstanceOf(HyperswitchUncertainException::class, $e);
            $this->assertSame(['code' => 'IR_19'], $e->getErrors());
            $this->assertStringContainsString('Partial refund is not supported', $e->getMessage());
        }
    }

    public function test_refund_timeout_and_server_error_are_uncertain(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));

        try {
            $this->refund();
            $this->fail('An exception was expected');
        } catch (HyperswitchUncertainException) {
            $this->assertTrue(true);
        }

        Http::fake(['hs.test/refunds' => Http::response(['error' => ['message' => 'boom']], 500)]);

        $this->expectException(HyperswitchUncertainException::class);
        $this->refund();
    }

    public function test_retrieve_refund_force_sync_and_unknown_refund(): void
    {
        Http::fake([
            'hs.test/refunds/'.self::REFUND_ID.'*' => Http::response(['refund_id' => self::REFUND_ID, 'status' => 'succeeded']),
            'hs.test/refunds/*' => Http::response(['error' => ['message' => 'Refund does not exist in our records']], 404),
        ]);

        $this->assertSame('succeeded', $this->client()->retrieveRefund(self::REFUND_ID, true)['status']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/refunds/'.self::REFUND_ID.'?force_sync=true'));

        try {
            $this->client()->retrieveRefund('ref_unknown');
            $this->fail('An exception was expected');
        } catch (ApiException $e) {
            $this->assertNotInstanceOf(HyperswitchUncertainException::class, $e);
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function test_retrieve_unknown_payment_is_a_404(): void
    {
        Http::fake(['hs.test/payments/*' => Http::response(['error' => ['message' => 'Payment does not exist in our records']], 404)]);

        try {
            $this->client()->retrievePayment(self::PAYMENT_ID);
            $this->fail('An exception was expected');
        } catch (ApiException $e) {
            $this->assertNotInstanceOf(HyperswitchUncertainException::class, $e);
            $this->assertSame(404, $e->getStatusCode());
        }
    }
}
