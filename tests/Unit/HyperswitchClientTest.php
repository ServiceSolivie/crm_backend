<?php

namespace Tests\Unit;

use App\Services\HyperswitchClient;
use PHPUnit\Framework\TestCase;

class HyperswitchClientTest extends TestCase
{
    private function client(): HyperswitchClient
    {
        return new HyperswitchClient(
            baseUrl: 'https://sandbox.hyperswitch.io',
            apiKey: 'snd_test',
            profileId: 'pro_test',
            connector: 'sogecommerce',
            returnUrl: 'https://crm.example.com/retour',
        );
    }

    public function test_base_url_may_already_end_with_payments(): void
    {
        $this->assertSame('https://api.example.fr', HyperswitchClient::normalizeBaseUrl('https://api.example.fr/payments'));
        $this->assertSame('https://api.example.fr', HyperswitchClient::normalizeBaseUrl('https://api.example.fr/payments/'));
        $this->assertSame('https://api.example.fr', HyperswitchClient::normalizeBaseUrl('https://api.example.fr/'));
        $this->assertSame('https://sandbox.hyperswitch.io', HyperswitchClient::normalizeBaseUrl('https://sandbox.hyperswitch.io'));
    }

    public function test_amounts_are_converted_to_cents(): void
    {
        $this->assertSame(99000, HyperswitchClient::toMinorUnits('990.00'));
        $this->assertSame(1999, HyperswitchClient::toMinorUnits('19.99'));
        $this->assertSame(1, HyperswitchClient::toMinorUnits('0.01'));
    }

    public function test_payload_is_a_3ds_card_redirect_routed_to_the_connector_and_profile(): void
    {
        $payload = $this->client()->buildPaymentPayload('pay_abcdefghijklmnopqrstuvwxyz', '990.00', 'EUR', 'client@example.com', 'PAY-12-1', 'lead-12', ['lead_id' => '12']);

        // Id chosen by the CRM, sent for idempotence
        $this->assertSame('pay_abcdefghijklmnopqrstuvwxyz', $payload['payment_id']);
        // E-mail in the new place (customer.email) and in the deprecated one
        $this->assertSame(['id' => 'lead-12', 'email' => 'client@example.com'], $payload['customer']);

        $this->assertSame(99000, $payload['amount']);
        $this->assertSame('EUR', $payload['currency']);
        $this->assertTrue($payload['confirm']);
        $this->assertSame('automatic', $payload['capture_method']);
        $this->assertSame('three_ds', $payload['authentication_type']);
        $this->assertSame(['sogecommerce'], $payload['connector']);
        $this->assertSame('pro_test', $payload['profile_id']);
        $this->assertSame('card_redirect', $payload['payment_method']);
        $this->assertSame('card_redirect', $payload['payment_method_type']);
        $this->assertSame('client@example.com', $payload['email']);
        $this->assertSame('https://crm.example.com/retour', $payload['return_url']);
        $this->assertSame('PAY-12-1', $payload['merchant_order_reference_id']);
        $this->assertSame(['crm_reference' => 'PAY-12-1', 'lead_id' => '12'], $payload['metadata']);
        // card_redirect must be serialised as an empty JSON object, not []
        $this->assertSame('{"card_redirect":{"card_redirect":{}}}', json_encode($payload['payment_method_data']));
    }

    public function test_generated_payment_ids_have_the_30_characters_hyperswitch_requires(): void
    {
        $ids = array_map(fn () => HyperswitchClient::generatePaymentId(), range(1, 50));

        foreach ($ids as $id) {
            $this->assertSame(30, strlen($id));
            $this->assertMatchesRegularExpression('/^pay_[A-Za-z0-9]{26}$/', $id);
        }
        $this->assertCount(50, array_unique($ids));
    }

    public function test_bank_error_prefers_the_sogecommerce_code_over_the_generic_one(): void
    {
        $this->assertSame(
            ['error_code' => '51', 'error_message' => 'Provision insuffisante'],
            HyperswitchClient::errorOf(['error_code' => '51', 'error_message' => 'Provision insuffisante', 'unified_code' => 'UE_9000', 'unified_message' => 'Declined']),
        );
        $this->assertSame(
            ['error_code' => 'UE_9000', 'error_message' => 'Declined'],
            HyperswitchClient::errorOf(['unified_code' => 'UE_9000', 'unified_message' => 'Declined']),
        );
        $this->assertSame(['error_code' => null, 'error_message' => null], HyperswitchClient::errorOf(['status' => 'succeeded']));
    }

    public function test_log_summary_keeps_identifiers_and_drops_personal_data(): void
    {
        $summary = HyperswitchClient::logSummary([
            'payment_id' => 'pay_1',
            'status' => 'failed',
            'connector' => 'sogecommerce',
            'merchant_connector_id' => 'mca_1',
            'connector_transaction_id' => 'uuid-1',
            'error_code' => '51',
            'email' => 'client@example.com',
            'customer' => ['id' => 'lead-1', 'email' => 'client@example.com', 'name' => 'Jean Dupont'],
            'billing' => ['address' => ['line1' => '10 rue de la Paix']],
            'client_secret' => 'pay_1_secret',
        ]);

        $this->assertSame(['payment_id', 'status', 'connector', 'merchant_connector_id', 'connector_transaction_id', 'error_code'], array_keys($summary));
        $this->assertStringNotContainsString('client@example.com', json_encode($summary));
        $this->assertStringNotContainsString('secret', json_encode($summary));
    }

    public function test_payment_url_is_read_from_the_redirect_or_the_payment_link(): void
    {
        $this->assertSame('https://pay/redirect', HyperswitchClient::extractPaymentUrl(['next_action' => ['redirect_to_url' => 'https://pay/redirect']]));
        $this->assertSame('https://pay/link', HyperswitchClient::extractPaymentUrl(['payment_link' => ['link' => 'https://pay/link']]));
        $this->assertNull(HyperswitchClient::extractPaymentUrl(['status' => 'failed']));
    }

    public function test_client_secret_is_never_stored(): void
    {
        $stored = HyperswitchClient::withoutSecrets([
            'payment_id' => 'pay_1',
            'client_secret' => 'pay_1_secret_x',
            // Base64 of "…,client_secret=pay_1_secret_x,…"
            'sdk_authorization' => 'cHJvZmlsZV9pZD1wcm9fMSxjbGllbnRfc2VjcmV0PXBheV8xX3NlY3JldF94',
        ]);

        $this->assertSame(['payment_id' => 'pay_1'], $stored);
    }

    public function test_link_expiry_is_read_from_expires_on(): void
    {
        $this->assertSame('2026-09-29 23:45:21', HyperswitchClient::expiresAtOf(['expires_on' => '2026-09-29T23:45:21.982Z'])->format('Y-m-d H:i:s'));
        $this->assertNull(HyperswitchClient::expiresAtOf([]));
        $this->assertNull(HyperswitchClient::expiresAtOf(['expires_on' => 'not a date']));
    }

    public function test_unused_link_is_recognised_by_psp_010_without_transaction(): void
    {
        // force_sync on PAY-33509-1, never paid: Sogecommerce has no transaction
        $this->assertTrue(HyperswitchClient::neverAttempted(['status' => 'failed', 'error_code' => 'PSP_010', 'connector_transaction_id' => null]));
        // A real refusal: bank code and / or a transaction at Sogecommerce
        $this->assertFalse(HyperswitchClient::neverAttempted(['status' => 'failed', 'error_code' => '51', 'connector_transaction_id' => null]));
        $this->assertFalse(HyperswitchClient::neverAttempted(['status' => 'failed', 'error_code' => 'PSP_010', 'connector_transaction_id' => 'uuid-1']));
        $this->assertFalse(HyperswitchClient::neverAttempted(['status' => 'requires_customer_action']));
    }

    public function test_client_without_key_or_profile_is_not_configured(): void
    {
        $this->assertTrue($this->client()->isConfigured());
        $this->assertFalse((new HyperswitchClient('https://x', null, 'pro_1', 'sogecommerce', 'https://r'))->isConfigured());
        $this->assertFalse((new HyperswitchClient('https://x', 'snd_1', null, 'sogecommerce', 'https://r'))->isConfigured());
    }
}
