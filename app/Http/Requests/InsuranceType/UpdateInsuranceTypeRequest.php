<?php

namespace App\Http\Requests\InsuranceType;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The code is what leads store: it never changes.
 */
class UpdateInsuranceTypeRequest extends FormRequest
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
            'label' => ['sometimes', 'string', 'max:80', Rule::unique('insurance_types', 'label')->ignore($this->route('insuranceType'))],
            'short_label' => ['sometimes', 'string', 'max:40'],
            'lead_page_id' => ['sometimes', 'nullable', 'integer', 'exists:lead_pages,id'],
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
        ];
    }
}
