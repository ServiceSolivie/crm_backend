<?php

namespace App\Http\Requests\LeadStatus;

use App\Enums\LeadStatusEnum;
use App\Support\LeadStatusCatalog;
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
        $leadStatusId = $this->route('leadStatus')?->id;

        return [
            'label' => [
                'sometimes',
                'string',
                'max:60',
                Rule::unique('lead_statuses', 'label')->ignore($leadStatusId),
                Rule::notIn(array_column(LeadStatusEnum::options(), 'label')),
            ],
            // Leads store the code: it is fixed once the status exists
            'code' => ['prohibited'],
            'stage' => ['sometimes', Rule::in(LeadStatusCatalog::CUSTOM_STAGES)],
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
            'code.prohibited' => 'Le code d\'un statut ne peut plus être modifié.',
            'stage.in' => 'Un statut personnalisé se place dans Contact, À relancer, Devis ou Perdu.',
        ];
    }
}
