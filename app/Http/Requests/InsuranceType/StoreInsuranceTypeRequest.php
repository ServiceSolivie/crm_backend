<?php

namespace App\Http\Requests\InsuranceType;

use Illuminate\Foundation\Http\FormRequest;

class StoreInsuranceTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:80', 'unique:insurance_types,label'],
            'short_label' => ['required', 'string', 'max:40'],
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z][A-Z0-9_]*$/', 'unique:insurance_types,code'],
            'lead_page_id' => ['nullable', 'integer', 'exists:lead_pages,id'],
            'is_active' => ['sometimes', 'boolean'],
            'requires_company_info' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'label.unique' => 'Un type d\'assurance porte déjà ce nom.',
            'code.regex' => 'Le code ne contient que des majuscules, des chiffres et « _ », et commence par une lettre.',
            'code.unique' => 'Un type d\'assurance utilise déjà ce code.',
        ];
    }
}
