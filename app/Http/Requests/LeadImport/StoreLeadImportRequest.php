<?php

namespace App\Http\Requests\LeadImport;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeadImportRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ];
    }
}
