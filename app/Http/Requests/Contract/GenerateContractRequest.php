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
            // Field-level whitelisting happens in ContractService against
            // the template's declared field keys.
            'data' => ['required', 'array'],
        ];
    }
}
