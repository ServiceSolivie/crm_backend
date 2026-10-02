<?php

namespace App\Http\Requests\Campaign;

use App\Enums\InsuranceTypeEnum;
use App\Models\Campaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lead_source_id' => ['sometimes', 'integer', 'exists:lead_sources,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'external_campaign_id' => ['sometimes', 'string', 'max:64'],
            'form_id' => ['sometimes', 'string', 'max:64'],
            'form_name' => ['nullable', 'string', 'max:255'],
            'insurance_type' => ['sometimes', Rule::in(InsuranceTypeEnum::values())],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Campaign $campaign */
            $campaign = $this->route('campaign');
            $exists = Campaign::query()
                ->where('lead_source_id', $this->input('lead_source_id', $campaign->lead_source_id))
                ->where('external_campaign_id', $this->input('external_campaign_id', $campaign->external_campaign_id))
                ->where('form_id', $this->input('form_id', $campaign->form_id))
                ->whereKeyNot($campaign->id)
                ->exists();

            if ($exists) {
                $validator->errors()->add('form_id', 'A campaign is already configured for this source, campaign ID, and form ID.');
            }
        }];
    }
}
