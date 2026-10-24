<?php

namespace App\Http\Requests\DvcType;

use App\Enums\InsuranceTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDvcTypeRequest extends FormRequest
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
            // One DVC type per insurance type
            'insurance_type' => ['required', Rule::enum(InsuranceTypeEnum::class), 'unique:dvc_types,insurance_type'],
            'label' => ['required', 'string', 'max:120'],
            'filename_prefix' => ['nullable', 'string', 'max:40', 'regex:/^[A-Z0-9][A-Z0-9-]*$/'],
            'is_active' => ['sometimes', 'boolean'],
            // Checked in depth by DvcSchemaValidator
            'schema' => ['required', 'array'],
            'change_note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'insurance_type.unique' => 'Ce type d\'assurance a déjà son DVC : modifiez-le au lieu d\'en créer un autre.',
            'filename_prefix.regex' => 'Le préfixe ne contient que des majuscules, des chiffres et « - ».',
        ];
    }
}
