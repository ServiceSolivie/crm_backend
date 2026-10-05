<?php

namespace App\Services\Acquisition;

use App\Exceptions\ApiException;
use App\Models\Campaign;
use App\Models\LeadSource;
use App\Models\LeadTouchpoint;
use App\Models\User;
use App\Services\LeadService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class GoogleAdsLeadIngestionService
{
    public function __construct(protected LeadService $leads, protected GoogleAdsLeadMapper $mapper) {}

    public function ingest(array $payload): void
    {
        $expected = config('services.google_ads.webhook_key');
        if (! $expected || ! hash_equals($expected, (string) $payload['google_key'])) {
            throw new ApiException('Unauthorized.', 401);
        }

        $source = LeadSource::query()->where('code', 'google_ads')->where('is_active', true)->first();
        if (! $source) {
            throw new ApiException('The Google Ads lead source is not configured.', 422);
        }
        $campaign = Campaign::query()->where('lead_source_id', $source->id)->where('external_campaign_id', (string) $payload['campaign_id'])->where('form_id', (string) $payload['form_id'])->where('is_active', true)->first();
        if (! $campaign && empty($payload['is_test'])) {
            throw new ApiException('This Google Ads campaign or form is not configured.', 422);
        }

        $existingTouchpoint = LeadTouchpoint::query()
            ->where('lead_source_id', $source->id)
            ->where('external_id', $payload['lead_id'])
            ->first();
        if ($existingTouchpoint) {
            $this->attachUnconfiguredTest($existingTouchpoint, $campaign, $payload);

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

                    return;
                }
                $touchpoint = $this->newTouchpoint($payload, $storedPayload, $source, $campaign);
                if (! empty($payload['is_test'])) {
                    $touchpoint->save();

                    return;
                }
                $systemUser = User::query()->whereKey(config('services.acquisition.system_user_id'))->where('is_active', true)->first();
                if (! $systemUser) {
                    throw new ApiException('The Acquisition system user is not configured.', 500);
                }
                $lead = $this->leads->createLead([...$this->mapper->leadData($payload), 'lead_source_id' => $source->id, 'campaign_id' => $campaign->id, 'insurance_type' => $campaign->insurance_type->value, 'lead_submitted_at' => $touchpoint->submitted_at], $systemUser);
                $touchpoint->lead_id = $lead->id;
                $touchpoint->save();
            });
        } catch (QueryException $e) {
            if (LeadTouchpoint::query()->where('lead_source_id', $source->id)->where('external_id', $payload['lead_id'])->exists()) {
                return;
            }
            throw $e;
        }
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
