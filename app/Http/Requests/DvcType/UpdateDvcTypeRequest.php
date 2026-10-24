<?php

namespace App\Http\Requests\DvcType;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Label, filename prefix and active flag. The schema changes by publishing a
 * version (PublishDvcSchemaRequest).
 */
class UpdateDvcTypeRequest extends FormRequest
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
            'label' => ['sometimes', 'required', 'string', 'max:120'],
            'filename_prefix' => ['sometimes', 'required', 'string', 'max:40', 'regex:/^[A-Z0-9][A-Z0-9-]*$/'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'filename_prefix.regex' => 'Le préfixe ne contient que des majuscules, des chiffres et « - ».',
        ];
    }
}
