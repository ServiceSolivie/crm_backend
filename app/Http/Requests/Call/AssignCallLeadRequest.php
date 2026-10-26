<?php

namespace App\Http\Requests\Call;

use Illuminate\Foundation\Http\FormRequest;

class AssignCallLeadRequest extends FormRequest
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
            'lead_id' => ['required', 'integer', 'exists:leads,id'],
        ];
    }
}
