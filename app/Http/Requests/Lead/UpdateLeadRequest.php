<?php

namespace App\Http\Requests\Lead;

use App\Enums\ClientTypeEnum;
use App\Enums\InsuranceTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeadRequest extends FormRequest
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
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'lead_source_id' => ['nullable', 'integer', 'exists:lead_sources,id'],
            'insurance_type' => ['sometimes', Rule::in(InsuranceTypeEnum::values())],
            'client_type' => ['nullable', Rule::in(ClientTypeEnum::values())],
            'company_status' => ['nullable', 'string', 'max:255'],
            'company_legal_form' => ['nullable', 'string', 'max:255'],
            'company_sector' => ['nullable', 'string', 'max:255'],
            'company_employee_count' => ['nullable', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_annual_revenue' => ['nullable', 'string', 'max:255'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'comment' => ['nullable', 'string'],
        ];
    }
}
