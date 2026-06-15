<?php

namespace App\Http\Requests\Lead;

use App\Enums\LeadStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeadStatusRequest extends FormRequest
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
            'status' => ['required', Rule::in(LeadStatusEnum::values())],
            'comment' => ['nullable', 'string'],
        ];
    }
}
