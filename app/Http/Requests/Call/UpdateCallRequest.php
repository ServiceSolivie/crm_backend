<?php

namespace App\Http\Requests\Call;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCallRequest extends FormRequest
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
            'note' => ['present', 'nullable', 'string', 'max:5000'],
        ];
    }
}
