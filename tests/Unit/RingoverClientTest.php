<?php

namespace Tests\Unit;

use App\Exceptions\RingoverException;
use App\Services\Ringover\RingoverClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RingoverClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.ringover.api_key' => 'test-key',
            'services.ringover.base_url' => 'https://public-api.ringover.com/v2',
        ]);
    }

    public function test_it_normalises_ringover_users(): void
    {
        Http::fake([
            'public-api.ringover.com/v2/users' => Http::response([
                'list_count' => 1,
                'list' => [[
                    'user_id' => 123456,
                    'firstname' => 'Pauline',
                    'lastname' => 'Martin',
                    'email' => ' Pauline.Martin@Example.com ',
                    'numbers' => [['number' => 33184800000], ['number' => 33612345678]],
                ]],
            ]),
        ]);

        $users = app(RingoverClient::class)->users();

        $this->assertSame([[
            'user_id' => '123456',
            'firstname' => 'Pauline',
            'lastname' => 'Martin',
            'email' => 'pauline.martin@example.com',
            'numbers' => ['+33184800000', '+33612345678'],
        ]], $users);

        // Ringover expects the raw key, without "Bearer".
        Http::assertSent(fn (Request $request) => $request->header('Authorization') === ['test-key']);
    }

    public function test_it_reports_a_rejected_api_key(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Unauthorized'], 401)]);

        $this->expectException(RingoverException::class);
        $this->expectExceptionMessage('Clé API Ringover refusée');

        app(RingoverClient::class)->testConnection();
    }

    public function test_media_is_only_fetched_from_ringover_over_https(): void
    {
        $this->assertTrue(RingoverClient::isRingoverUrl('https://cdn.ringover.com/records/a.mp3'));
        $this->assertTrue(RingoverClient::isRingoverUrl('https://ringover.com/x'));
        $this->assertFalse(RingoverClient::isRingoverUrl('http://cdn.ringover.com/records/a.mp3'));
        $this->assertFalse(RingoverClient::isRingoverUrl('https://ringover.com.evil.test/a.mp3'));
        $this->assertFalse(RingoverClient::isRingoverUrl('https://evilringover.com/a.mp3'));
        $this->assertFalse(RingoverClient::isRingoverUrl('https://127.0.0.1/a.mp3'));

        Http::fake();
        $this->expectException(RingoverException::class);

        try {
            app(RingoverClient::class)->fetchMedia('https://169.254.169.254/latest/meta-data');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_it_downloads_media_from_ringover(): void
    {
        Http::fake(['cdn.ringover.com/*' => Http::response('MP3DATA', 200, ['Content-Type' => 'audio/mpeg'])]);

        $response = app(RingoverClient::class)->fetchMedia('https://cdn.ringover.com/records/a.mp3');

        $this->assertSame('MP3DATA', $response->body());
        $this->assertSame('audio/mpeg', $response->header('Content-Type'));
    }

    public function test_callback_sends_numbers_as_ringover_digits_and_is_not_retried(): void
    {
        Http::fake(['*' => Http::response(['message' => 'error'], 500)]);

        try {
            app(RingoverClient::class)->requestCallback('+33184800001', '+33612345678');
            $this->fail('Expected a RingoverException');
        } catch (RingoverException) {
            // expected
        }

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://public-api.ringover.com/v2/callback'
            && $request['from_number'] === 33184800001
            && $request['to_number'] === 33612345678
            && $request['device'] === 'ALL');
    }

    public function test_it_refuses_to_call_ringover_without_a_key(): void
    {
        config(['services.ringover.api_key' => null]);
        Http::fake();

        $client = app(RingoverClient::class);
        $this->assertFalse($client->isConfigured());

        try {
            $client->users();
            $this->fail('Expected a RingoverException');
        } catch (RingoverException $e) {
            $this->assertSame(503, $e->getStatusCode());
        }

        Http::assertNothingSent();
    }
}
