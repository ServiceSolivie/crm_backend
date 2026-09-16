<?php

namespace App\Http\Requests\Lead;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FlagLeadIssueRequest extends FormRequest
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
            'issue_types' => ['required', 'array', 'min:1'],
            'issue_types.*' => [Rule::in(['documents_manquants', 'information_manquante', 'information_incorrecte'])],
            'missing_documents' => ['nullable', 'array'],
            'missing_documents.*' => ['string'],
            'comment' => ['nullable', 'string'],
        ];
    }
}
