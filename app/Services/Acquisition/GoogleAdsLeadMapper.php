<?php

namespace App\Services\Acquisition;

use App\Exceptions\ApiException;

class GoogleAdsLeadMapper
{
    public function leadData(array $payload): array
    {
        $columns = collect($payload['user_column_data'])->mapWithKeys(fn ($field) => [strtoupper((string) ($field['column_id'] ?? '')) => trim((string) ($field['string_value'] ?? ''))]);
        $first = $columns->get('FIRST_NAME');
        $last = $columns->get('LAST_NAME');
        if ((! $first || ! $last) && ($full = $columns->get('FULL_NAME'))) {
            $parts = preg_split('/\s+/', $full, 2);
            $first ??= $parts[0] ?? null;
            $last ??= $parts[1] ?? null;
        }
        $phone = $columns->get('PHONE_NUMBER');
        if (! $first || ! $last || ! $phone) {
            throw new ApiException('The Google Ads form must contain a first name, last name, and phone number.', 422);
        }

        return ['first_name' => $first, 'last_name' => $last, 'phone' => $phone, 'email' => $columns->get('EMAIL'), 'city' => $columns->get('CITY') ?? $columns->get('POSTAL_CODE'), 'address' => $columns->get('STREET_ADDRESS')];
    }
}
