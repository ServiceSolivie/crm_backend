<?php

namespace App\Http\Requests\Call;

use Illuminate\Foundation\Http\FormRequest;

class LinkRingoverCallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Ringover call ids are numeric but exceed JavaScript/PHP integer
     * precision, so they are always sent as strings.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ringover_call_id' => ['required', 'string', 'max:64', 'regex:/^[0-9A-Za-z_-]+$/'],
        ];
    }
}
