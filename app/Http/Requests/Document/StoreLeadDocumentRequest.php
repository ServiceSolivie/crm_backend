<?php

namespace App\Http\Requests\Document;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadDocumentRequest extends FormRequest
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
            'document_type' => ['required', 'string', Rule::exists('document_types', 'name')],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
        ];
    }
}
