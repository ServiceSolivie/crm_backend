<?php

namespace App\Http\Requests\Credential;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'partner_id' => ['sometimes', 'integer', 'exists:partners,id'],
            'label' => ['sometimes', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
