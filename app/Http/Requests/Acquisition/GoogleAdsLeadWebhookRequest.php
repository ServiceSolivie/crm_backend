<?php

namespace App\Http\Requests\Acquisition;

use App\Enums\ActivityEventEnum;
use App\Services\ActivityLogger;
use Illuminate\Contracts\Validation\Validator;
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

    /**
     * An unreadable request is refused before the key is even checked: it
     * is kept in the activity journal (which fields, never their values),
     * at most once a minute per address.
     */
    protected function failedValidation(Validator $validator): void
    {
        $activity = app(ActivityLogger::class);

        if ($activity->allowed('gads-invalid-payload|'.$this->ip(), 60)) {
            $fields = array_keys($validator->errors()->toArray());

            $activity->googleAds(null, ActivityEventEnum::GOOGLE_ADS_REJECTED, 'Requête illisible, champs absents ou invalides : '.implode(', ', array_slice($fields, 0, 8)).'.', [
                'reason' => 'invalid_payload',
                'fields' => $fields,
                'http_status' => 422,
            ]);
        }

        parent::failedValidation($validator);
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
