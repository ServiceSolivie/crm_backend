<?php

namespace App\Http\Requests\Campaign;

use App\Enums\InsuranceTypeEnum;
use App\Models\Campaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lead_source_id' => ['required', 'integer', 'exists:lead_sources,id'],
            'name' => ['required', 'string', 'max:255'],
            'external_campaign_id' => ['required', 'string', 'max:64'],
            'form_id' => ['required', 'string', 'max:64'],
            'form_name' => ['nullable', 'string', 'max:255'],
            'insurance_type' => ['required', Rule::in(InsuranceTypeEnum::values())],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $exists = Campaign::query()
                ->where('lead_source_id', $this->integer('lead_source_id'))
                ->where('external_campaign_id', $this->string('external_campaign_id')->toString())
                ->where('form_id', $this->string('form_id')->toString())
                ->exists();

            if ($exists) {
                $validator->errors()->add('form_id', 'A campaign is already configured for this source, campaign ID, and form ID.');
            }
        }];
    }
}
