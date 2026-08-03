<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
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
            return ApiResponse::error('Unauthorized.', 401);
        }

        return $next($request);
    }
}
