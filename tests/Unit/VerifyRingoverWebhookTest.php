<?php

namespace Tests\Unit;

use App\Http\Middleware\VerifyRingoverWebhook;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class VerifyRingoverWebhookTest extends TestCase
{
    protected const SECRET = 'test-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.ringover.webhook_secret' => self::SECRET]);
    }

    protected function responseStatus(array $headers): int
    {
        $request = Request::create('/api/v1/webhooks/ringover', 'POST', server: collect($headers)
            ->mapWithKeys(fn ($v, $k) => ['HTTP_'.strtoupper(str_replace('-', '_', $k)) => $v])->all());

        return (new VerifyRingoverWebhook)->handle($request, fn () => new Response('ok'))->getStatusCode();
    }

    protected function jwt(string $secret, int $exp, string $alg = 'HS512'): string
    {
        $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $h = $b64(json_encode(['alg' => $alg, 'typ' => 'JWT']));
        $p = $b64(json_encode(['exp' => $exp]));

        return "$h.$p.".$b64(hash_hmac('sha512', "$h.$p", $secret, true));
    }

    public function test_it_accepts_a_valid_jwt(): void
    {
        $this->assertSame(200, $this->responseStatus(['x-ringover-webhook-signature' => $this->jwt(self::SECRET, time() + 60)]));
    }

    public function test_it_rejects_forged_expired_or_wrong_algorithm_jwts(): void
    {
        $this->assertSame(401, $this->responseStatus(['x-ringover-webhook-signature' => $this->jwt('other', time() + 60)]));
        $this->assertSame(401, $this->responseStatus(['x-ringover-webhook-signature' => $this->jwt(self::SECRET, time() - 3600)]));
        $this->assertSame(401, $this->responseStatus(['x-ringover-webhook-signature' => $this->jwt(self::SECRET, time() + 60, 'none')]));
    }

    public function test_it_accepts_the_api_key_modes(): void
    {
        $this->assertSame(200, $this->responseStatus(['x-ringover-webhook-signature' => self::SECRET]));
        $this->assertSame(200, $this->responseStatus(['Authorization' => 'Bearer '.base64_encode(self::SECRET)]));
        $this->assertSame(401, $this->responseStatus(['Authorization' => 'Bearer '.base64_encode('nope')]));
    }

    public function test_it_rejects_unsigned_requests_and_refuses_when_not_configured(): void
    {
        $this->assertSame(401, $this->responseStatus([]));

        config(['services.ringover.webhook_secret' => null]);
        $this->assertSame(503, $this->responseStatus(['x-ringover-webhook-signature' => self::SECRET]));
    }
}
