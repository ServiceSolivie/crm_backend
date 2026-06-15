<?php

namespace App\Http\Requests\Lead;

use App\Enums\InsuranceTypeEnum;
use App\Enums\LeadStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'lead_source_id' => ['nullable', 'integer', 'exists:lead_sources,id'],
            'insurance_type' => ['required', Rule::in(InsuranceTypeEnum::values())],
            'status' => ['sometimes', Rule::in(LeadStatusEnum::values())],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'comment' => ['nullable', 'string'],
        ];
    }
}
