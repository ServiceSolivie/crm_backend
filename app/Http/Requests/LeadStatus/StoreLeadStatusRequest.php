<?php

namespace App\Http\Requests\LeadStatus;

use App\Enums\LeadStatusEnum;
use App\Support\LeadStatusCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadStatusRequest extends FormRequest
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
            'label' => ['required', 'string', 'max:60', 'unique:lead_statuses,label', Rule::notIn(array_column(LeadStatusEnum::options(), 'label'))],
            'code' => [
                'required',
                'string',
                'max:40',
                'regex:/^[A-Z][A-Z0-9_]*$/',
                'unique:lead_statuses,code',
                // A code of the system belongs to the system, now and later
                Rule::notIn(LeadStatusEnum::values()),
            ],
            'stage' => ['required', Rule::in(LeadStatusCatalog::CUSTOM_STAGES)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'label.unique' => 'Un statut porte déjà ce libellé.',
            'label.not_in' => 'Ce statut existe déjà dans le système.',
            'code.regex' => 'Le code ne contient que des majuscules, des chiffres et « _ », et commence par une lettre.',
            'code.unique' => 'Un statut utilise déjà ce code.',
            'code.not_in' => 'Ce code est celui d\'un statut du système.',
            'stage.in' => 'Un statut personnalisé se place dans Contact, À relancer, Devis ou Perdu.',
        ];
    }
}
