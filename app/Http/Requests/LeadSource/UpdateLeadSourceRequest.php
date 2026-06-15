<?php

namespace App\Http\Requests\LeadSource;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeadSourceRequest extends FormRequest
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
        $leadSourceId = $this->route('leadSource')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('lead_sources', 'code')->ignore($leadSourceId)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
