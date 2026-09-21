<?php

namespace App\Http\Requests\Lead;

use App\Enums\LeadStatusEnum;
use App\Rules\ReviewStatusAllowed;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkLeadRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer', 'distinct'],
            'action' => ['required', Rule::in(['assign', 'status', 'delete'])],
            'assigned_to' => ['required_if:action,assign', 'nullable', 'integer', 'exists:users,id'],
            'status' => [
                'required_if:action,status',
                'nullable',
                Rule::in(LeadStatusEnum::values()),
                // Validation needs a revenue per lead: done one lead at a time
                Rule::notIn([LeadStatusEnum::VALIDE->value]),
                new ReviewStatusAllowed,
            ],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.not_in' => 'La validation se fait lead par lead (le chiffre d’affaires est demandé pour chacun).',
        ];
    }
}
