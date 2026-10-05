<?php

namespace App\Http\Requests\Acquisition;

use Illuminate\Foundation\Http\FormRequest;

class GoogleAdsLeadWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lead_id' => ['required', 'string', 'max:255'],
            'google_key' => ['required', 'string'],
            'campaign_id' => $this->identifierRules(),
            'form_id' => $this->identifierRules(),
            'is_test' => ['nullable', 'boolean'],
            'lead_submit_time' => ['nullable', 'date'], 'gcl_id' => ['nullable', 'string', 'max:255'],
            'adgroup_id' => ['nullable', ...$this->identifierRules(false)],
            'creative_id' => ['nullable', ...$this->identifierRules(false)],
            'user_column_data' => ['required', 'array'],
            'user_column_data.*.column_id' => ['nullable', 'string', 'max:100'],
            'user_column_data.*.string_value' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function identifierRules(bool $required = true): array
    {
        return [
            ...($required ? ['required'] : []),
            function (string $attribute, mixed $value, \Closure $fail) {
                if (! is_scalar($value) || ! preg_match('/^\d{1,64}$/', (string) $value)) {
                    $fail("The {$attribute} must be a numeric identifier.");
                }
            },
        ];
    }
}
