<?php

namespace App\Http\Requests\Lead;

use App\Enums\LeadStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeadStatusRequest extends FormRequest
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
            'status' => ['required', Rule::in(LeadStatusEnum::values())],
            'comment' => ['nullable', 'string'],
            'expected_revenue' => [
                Rule::requiredIf($this->input('status') === LeadStatusEnum::VALIDE->value),
                'nullable',
                'numeric',
                'min:0.01',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'expected_revenue.required_if' => 'Le montant attendu est obligatoire pour valider un lead.',
            'expected_revenue.min' => 'Le montant attendu doit être supérieur à 0.',
        ];
    }
}
