<?php

namespace App\Http\Requests\Lead;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class AssignLeadRequest extends FormRequest
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
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * Leads are dispatched to agents only — team leaders/managers/gestion
     * dispatch leads, they don't receive them. The frontend's assign
     * dropdown already only ever lists agents; this closes the same
     * rule off for direct API calls.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $userId = $this->input('assigned_to');

            if ($userId && ! User::find($userId)?->hasRole(RoleEnum::AGENT->value)) {
                $validator->errors()->add('assigned_to', 'Les leads ne peuvent être assignés qu\'à un agent.');
            }
        });
    }
}
