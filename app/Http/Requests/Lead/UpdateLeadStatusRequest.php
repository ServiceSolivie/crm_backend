<?php

namespace App\Http\Requests\Lead;

use App\Enums\LeadStatusEnum;
use App\Rules\ReviewStatusAllowed;
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
            'status' => ['required', Rule::in(LeadStatusEnum::values()), new ReviewStatusAllowed],
            'comment' => ['nullable', 'string'],
            // Optional: the contract total normally comes with the first payment
            'expected_revenue' => [
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
