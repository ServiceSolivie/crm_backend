<?php

namespace App\Http\Requests\Contract;

use Illuminate\Foundation\Http\FormRequest;

class GenerateContractRequest extends FormRequest
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
            'template_key' => ['required', 'string'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            // The DVC schema version the form was opened with (default: current)
            'dvc_type_version_id' => ['nullable', 'integer'],
            // Field-level whitelisting and validation happen in ContractService
            // against the DVC type's schema.
            'data' => ['required', 'array'],
        ];
    }
}
