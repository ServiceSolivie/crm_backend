<?php

namespace App\Services\Acquisition;

use App\Enums\ActivityEventEnum;
use App\Exceptions\ApiException;
use App\Models\Campaign;
use App\Models\LeadSource;
use App\Models\LeadTouchpoint;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\LeadService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class GoogleAdsLeadIngestionService
{
    /** Why the current submission is being refused (activity journal) */
    protected ?string $rejection = null;

    public function __construct(protected LeadService $leads, protected GoogleAdsLeadMapper $mapper, protected ActivityLogger $activity) {}

    /**
     * Handle one Google Ads lead form submission. Whatever happens to it
     * (lead created, test, duplicate, refused and why) is written to the
     * activity journal.
     *
     * @throws ApiException
     */
    public function ingest(array $payload): void
    {
        $this->rejection = null;

        try {
            $this->process($payload);
        } catch (ApiException $e) {
            // Outside the transaction, so the refusal is kept even though the lead was rolled back
            $this->logRejection($payload, $e);

            throw $e;
        }
    }

    protected function process(array $payload): void
    {
        $expected = config('services.google_ads.webhook_key');
        if (! $expected || ! hash_equals($expected, (string) $payload['google_key'])) {
            throw $this->reject('invalid_key', 'Unauthorized.', 401);
        }

        $source = LeadSource::query()->where('code', 'google_ads')->where('is_active', true)->first();
        if (! $source) {
            throw $this->reject('source_not_configured', 'The Google Ads lead source is not configured.', 422);
        }
        $campaign = Campaign::query()->where('lead_source_id', $source->id)->where('external_campaign_id', (string) $payload['campaign_id'])->where('form_id', (string) $payload['form_id'])->where('is_active', true)->first();
        if (! $campaign && empty($payload['is_test'])) {
            throw $this->reject('campaign_not_configured', 'This Google Ads campaign or form is not configured.', 422);
        }

        $existingTouchpoint = LeadTouchpoint::query()
            ->where('lead_source_id', $source->id)
            ->where('external_id', $payload['lead_id'])
            ->first();
        if ($existingTouchpoint) {
            $this->attachUnconfiguredTest($existingTouchpoint, $campaign, $payload);
            $this->logDuplicate($payload, $campaign, $existingTouchpoint);

            return;
        }

        $storedPayload = $payload;
        unset($storedPayload['google_key']);
        try {
            DB::transaction(function () use ($payload, $storedPayload, $source, $campaign) {
                $existingTouchpoint = LeadTouchpoint::query()
                    ->where('lead_source_id', $source->id)
                    ->where('external_id', $payload['lead_id'])
                    ->lockForUpdate()
                    ->first();
                if ($existingTouchpoint) {
                    $this->attachUnconfiguredTest($existingTouchpoint, $campaign, $payload);
                    $this->logDuplicate($payload, $campaign, $existingTouchpoint);

                    return;
                }
                $touchpoint = $this->newTouchpoint($payload, $storedPayload, $source, $campaign);
                if (! empty($payload['is_test'])) {
                    $touchpoint->save();
                    $this->activity->googleAds((string) $payload['lead_id'], ActivityEventEnum::GOOGLE_ADS_TEST_RECEIVED, $campaign
                        ? "Envoi de test reçu pour la campagne « {$campaign->name} » : aucun lead créé."
                        : 'Envoi de test reçu (campagne non configurée dans le CRM) : aucun lead créé.', $this->submissionDetails($payload, $campaign), $campaign?->name);

                    return;
                }
                $systemUser = User::query()->whereKey(config('services.acquisition.system_user_id'))->where('is_active', true)->first();
                if (! $systemUser) {
                    throw $this->reject('system_user_missing', 'The Acquisition system user is not configured.', 500);
                }
                $lead = $this->leads->createLead([...$this->mapper->leadData($payload), 'lead_source_id' => $source->id, 'campaign_id' => $campaign->id, 'insurance_type' => $campaign->insurance_type->value, 'lead_submitted_at' => $touchpoint->submitted_at], $systemUser);
                $touchpoint->lead_id = $lead->id;
                $touchpoint->save();

                $name = trim($lead->first_name.' '.$lead->last_name);
                $this->activity->googleAds((string) $payload['lead_id'], ActivityEventEnum::GOOGLE_ADS_LEAD_CREATED, "{$name} · campagne « {$campaign->name} » · {$campaign->insurance_type->value}", [
                    ...$this->submissionDetails($payload, $campaign),
                    'lead_reference' => $lead->reference,
                ], "{$name} · {$campaign->name}", $lead->id);
            });
        } catch (QueryException $e) {
            if (LeadTouchpoint::query()->where('lead_source_id', $source->id)->where('external_id', $payload['lead_id'])->exists()) {
                $this->logDuplicate($payload, $campaign, null);

                return;
            }
            throw $e;
        }
    }

    /* ── Activity journal ────────────────────────────────────────────── */

    /**
     * The exception of a refusal, remembering why for the journal.
     */
    protected function reject(string $reason, string $message, int $status): ApiException
    {
        $this->rejection = $reason;

        return new ApiException($message, $status);
    }

    /**
     * A refused submission. A wrong key is not a trusted request: it can't
     * name its own operation, and is logged at most once a minute per
     * address. The key itself is never kept.
     */
    protected function logRejection(array $payload, ApiException $e): void
    {
        // The form mapper refuses on its own (no first name, last name or phone)
        $reason = $this->rejection ?? ($e->getStatusCode() === 422 ? 'incomplete_form' : 'error');
        $trusted = $reason !== 'invalid_key';
        $googleLeadId = $trusted ? (string) ($payload['lead_id'] ?? '') : null;

        if (! $this->activity->allowed('gads-rejected|'.request()->ip().'|'.$reason.'|'.$googleLeadId, 60)) {
            return;
        }

        $message = match ($reason) {
            'invalid_key' => 'Clé Google Ads absente ou incorrecte : requête refusée.',
            'source_not_configured' => "La source « Google Ads » n'existe pas ou est inactive dans le CRM.",
            'campaign_not_configured' => "Campagne ou formulaire non configuré dans le CRM (campagne {$payload['campaign_id']}, formulaire {$payload['form_id']}) : le lead n'a pas été créé.",
            'incomplete_form' => "Formulaire sans prénom, nom ou téléphone : le lead n'a pas été créé.",
            'system_user_missing' => "Le compte technique d'acquisition n'est pas configuré : le lead n'a pas été créé.",
            default => mb_substr($e->getMessage(), 0, 300),
        };

        $this->activity->googleAds($googleLeadId, ActivityEventEnum::GOOGLE_ADS_REJECTED, $message, [
            ...($trusted ? $this->submissionDetails($payload, null) : []),
            'reason' => $reason,
            'http_status' => $e->getStatusCode(),
        ], $trusted ? "Campagne {$payload['campaign_id']} · formulaire {$payload['form_id']}" : null);
    }

    protected function logDuplicate(array $payload, ?Campaign $campaign, ?LeadTouchpoint $touchpoint): void
    {
        $this->activity->googleAds((string) $payload['lead_id'], ActivityEventEnum::GOOGLE_ADS_DUPLICATE_IGNORED, 'Envoi déjà reçu : aucun second lead créé.', [
            ...$this->submissionDetails($payload, $campaign),
            'first_received_at' => $touchpoint?->received_at?->toIso8601String(),
        ], $campaign?->name, $touchpoint?->lead_id);
    }

    /**
     * What the journal keeps about a submission: its Google identifiers and
     * the CRM campaign, never the key nor the person's answers.
     *
     * @return array<string, mixed>
     */
    protected function submissionDetails(array $payload, ?Campaign $campaign): array
    {
        return [
            'google_lead_id' => (string) ($payload['lead_id'] ?? ''),
            'campaign_id' => isset($payload['campaign_id']) ? (string) $payload['campaign_id'] : null,
            'form_id' => isset($payload['form_id']) ? (string) $payload['form_id'] : null,
            'is_test' => ! empty($payload['is_test']) ?: null,
            'crm_campaign' => $campaign?->name,
            'insurance_type' => $campaign?->insurance_type?->value,
            'gcl_id' => $payload['gcl_id'] ?? null,
        ];
    }

    protected function newTouchpoint(array $payload, array $storedPayload, LeadSource $source, ?Campaign $campaign): LeadTouchpoint
    {
        return new LeadTouchpoint(['lead_source_id' => $source->id, 'campaign_id' => $campaign?->id, 'external_id' => $payload['lead_id'], 'payload' => $storedPayload, 'is_test' => (bool) ($payload['is_test'] ?? false), 'submitted_at' => ! empty($payload['lead_submit_time']) ? Carbon::parse($payload['lead_submit_time']) : null, 'received_at' => now(), 'gcl_id' => $payload['gcl_id'] ?? null, 'adgroup_id' => isset($payload['adgroup_id']) ? (string) $payload['adgroup_id'] : null, 'creative_id' => isset($payload['creative_id']) ? (string) $payload['creative_id'] : null]);
    }

    protected function attachUnconfiguredTest(LeadTouchpoint $touchpoint, ?Campaign $campaign, array $payload): void
    {
        if (! empty($payload['is_test']) && ! $touchpoint->campaign_id && $campaign) {
            $touchpoint->update(['campaign_id' => $campaign->id]);
        }
    }
}
