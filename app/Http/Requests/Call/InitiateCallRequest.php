<?php

namespace App\Http\Requests\Call;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InitiateCallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * "phone": dialled by the Ringover phone embedded in the CRM (default).
     * "mobile": Ringover rings the agent's own devices first, then the lead.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'via' => ['nullable', Rule::in(['phone', 'mobile'])],
        ];
    }
}
