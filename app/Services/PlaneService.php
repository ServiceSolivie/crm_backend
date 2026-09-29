<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\PaymentSession;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class PlaneService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{id: string}
     */
    public function createWorkItem(array $payload): array
    {
        $config = config('services.plane');
        foreach (['base_url', 'api_key', 'workspace', 'project_id'] as $key) {
            if (empty($config[$key])) {
                throw new ApiException(__('messages.plane.unavailable'), 503);
            }
        }

        if (parse_url($config['base_url'], PHP_URL_SCHEME) !== 'https') {
            throw new ApiException(__('messages.plane.unavailable'), 503);
        }

        $url = rtrim($config['base_url'], '/').'/api/v1/workspaces/'
            .rawurlencode($config['workspace']).'/projects/'
            .rawurlencode($config['project_id']).'/work-items/';

        try {
            $response = Http::acceptJson()
                ->withHeaders(['X-API-Key' => $config['api_key']])
                ->connectTimeout(3)
                ->timeout(10)
                ->withoutRedirecting()
                ->post($url, $payload);
        } catch (ConnectionException) {
            // Do not log exception text: upstream details may include credentials or content.
            Log::warning('Plane work item creation could not be confirmed.', ['reason' => 'connection']);
            throw new ApiException(__('messages.plane.unconfirmed'), 504);
        }

        if ($response->status() !== 201 || ! is_string($response->json('id')) || ! Str::isUuid($response->json('id'))) {
            Log::warning('Plane work item creation returned an unexpected response.', ['status' => $response->status()]);
            throw new ApiException(__('messages.plane.failed'), 502);
        }

        return ['id' => $response->json('id')];
    }

    /**
     * Create a Plane work item for a failed Hyperswitch payment response.
     * Other payment statuses deliberately do not create a work item.
     *
     * @param  array<string, mixed>  $payment
     * @return array{id: string}|null
     */
    public function reportFailedHyperswitchPayment(array $payment): ?array
    {
        if (($payment['status'] ?? null) !== 'failed') {
            return null;
        }

        $state = config('services.plane.payment_failure.state_id');
        $label = config('services.plane.payment_failure.label_id');

        if (! $state || ! $label) {
            throw new ApiException(__('messages.plane.unavailable'), 503);
        }

        $paymentId = $payment['payment_id'] ?? null;
        $title = 'Paiement Hyperswitch échoué'.($paymentId ? " · {$paymentId}" : '');
        $payloadJson = json_encode(
            $payment,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
        );

        if ($payloadJson === false) {
            throw new ApiException(__('messages.plane.failed'), 502);
        }

        return $this->createWorkItem([
            'name' => $title,
            'description_html' => view('plane.hyperswitch-payment-failure', [
                'payment' => $payment,
                'payloadJson' => $payloadJson,
            ])->render(),
            'state' => $state,
            'labels' => [$label],
        ]);
    }

    /**
     * Create a Plane work item when a pending payment couldn't be checked
     * with Hyperswitch before sending a new link (the agent is blocked).
     *
     * @return array{id: string}
     */
    public function reportHyperswitchCheckFailure(PaymentSession $session, Throwable $error): array
    {
        $state = config('services.plane.payment_failure.state_id');
        $label = config('services.plane.payment_failure.label_id');

        if (! $state || ! $label) {
            throw new ApiException(__('messages.plane.unavailable'), 503);
        }

        return $this->createWorkItem([
            'name' => "Paiement Hyperswitch non vérifiable · {$session->reference}",
            'description_html' => view('plane.hyperswitch-check-failure', [
                'session' => $session,
                'error' => $error,
            ])->render(),
            'state' => $state,
            'labels' => [$label],
        ]);
    }
}
