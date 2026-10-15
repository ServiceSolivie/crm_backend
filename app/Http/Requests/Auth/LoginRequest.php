<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\ChangePasswordRequest;

class LoginRequest extends FormRequest
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
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
            // Passkey id the browser remembers for this machine (approved machines)
            'device_credential_id' => ['nullable', 'string', 'max:512'],
            // "Remember this machine" mark from the last PIN check
            'device_mark' => ['nullable', 'string', 'max:100'],
        ];
    }
}
