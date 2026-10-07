<?php

namespace App\Services\Acquisition;

use App\Exceptions\ApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleAdsLegacyForwarder
{
    public function forward(string $rawPayload, string $externalId): void
    {
        $config = config('services.google_ads.legacy_forward');
        if (! $config['enabled']) {
            return;
        }

        $url = $config['url'] ?? null;
        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            throw new ApiException('The Google Ads legacy forwarding URL is not configured.', 503);
        }

        try {
            $response = Http::acceptJson()
                ->connectTimeout(3)
                ->timeout(10)
                ->withBody($rawPayload, 'application/json')
                ->post($url);
        } catch (ConnectionException) {
            Log::warning('google_ads_legacy_forward: destination could not be reached', [
                'external_id' => $externalId,
            ]);

            throw new ApiException('The Google Ads legacy destination could not be reached.', 502);
        }

        if (! $response->successful()) {
            Log::warning('google_ads_legacy_forward: destination returned an error', [
                'external_id' => $externalId,
                'http_status' => $response->status(),
            ]);

            throw new ApiException('The Google Ads legacy destination could not be confirmed.', 502);
        }
    }
}
