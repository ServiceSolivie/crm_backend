<?php

namespace App\Http\Requests\DocumentRequirement;

use App\Enums\ClientTypeEnum;
use App\Enums\InsuranceTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncRequirementsRequest extends FormRequest
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
            'insurance_type' => ['required', 'string', Rule::in(InsuranceTypeEnum::values())],
            'client_type' => ['nullable', 'string', Rule::in(ClientTypeEnum::values())],
            'document_type_ids' => ['present', 'array'],
            'document_type_ids.*' => ['integer', Rule::exists('document_types', 'id')],
        ];
    }
}
