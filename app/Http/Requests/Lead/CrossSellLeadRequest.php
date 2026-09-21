<?php

namespace App\Http\Requests\Lead;

use App\Enums\ClientTypeEnum;
use App\Enums\InsuranceTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrossSellLeadRequest extends FormRequest
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
            'insurance_type' => ['required', Rule::in(InsuranceTypeEnum::values())],
            'client_type' => ['nullable', Rule::in(ClientTypeEnum::values())],
            'comment' => ['nullable', 'string'],
        ];
    }
}
