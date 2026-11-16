<?php

namespace App\Http\Requests\Lead;

use App\Enums\ClientTypeEnum;
use App\Support\InsuranceTypeCatalog;
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
            'insurance_type' => ['required', Rule::in(app(InsuranceTypeCatalog::class)->selectable())],
            'client_type' => ['nullable', Rule::in(ClientTypeEnum::values())],
            'comment' => ['nullable', 'string'],
        ];
    }
}
