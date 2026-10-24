<?php

namespace App\Http\Requests\DvcType;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A schema sent to be checked, previewed or published as a new version.
 */
class DvcSchemaRequest extends FormRequest
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
            // Checked in depth by DvcSchemaValidator
            'schema' => ['required', 'array'],
            'change_note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
