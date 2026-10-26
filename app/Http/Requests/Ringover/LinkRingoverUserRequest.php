<?php

namespace App\Http\Requests\Ringover;

use Illuminate\Foundation\Http\FormRequest;

class LinkRingoverUserRequest extends FormRequest
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
            'ringover_user_id' => ['required', 'string', 'max:64'],
            'ringover_number' => ['nullable', 'string', 'max:30'],
        ];
    }
}
