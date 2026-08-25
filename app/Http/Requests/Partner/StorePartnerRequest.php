<?php

namespace App\Http\Requests\Partner;

use Illuminate\Foundation\Http\FormRequest;

class StorePartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'login_url' => ['required', 'string', 'url', 'max:2048'],
            'domain' => ['required', 'string', 'max:255'],
            'form_selector' => ['nullable', 'string', 'max:500'],
            'identity_selector' => ['nullable', 'string', 'max:500'],
            'password_selector' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'form_selector.required_with' => 'Les trois sélecteurs doivent être remplis ensemble.',
            'identity_selector.required_with' => 'Les trois sélecteurs doivent être remplis ensemble.',
            'password_selector.required_with' => 'Les trois sélecteurs doivent être remplis ensemble.',
        ];
    }
}
