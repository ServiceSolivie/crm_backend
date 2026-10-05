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
