<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSecret
{
    /**
     * Gates the Google Sheets webhook with a shared secret sent by the
     * Apps Script on every call. hash_equals() avoids leaking the secret
     * length/content via response-timing differences.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.google.webhook_secret');
        $provided = (string) $request->header('X-Webhook-Secret');

        if (! $expected || ! hash_equals($expected, $provided)) {
            Log::warning('google_sheets_webhook: secret mismatch', [
                'header_present' => $request->hasHeader('X-Webhook-Secret'),
                'provided_length' => strlen($provided),
                'expected_length' => strlen((string) $expected),
                'provided_prefix' => substr($provided, 0, 4),
                'expected_prefix' => substr((string) $expected, 0, 4),
            ]);

            return ApiResponse::error('Unauthorized.', 401);
        }

        return $next($request);
    }
}
