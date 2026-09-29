<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContractTotalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The new contract total and why it changes (kept in the lead's notes).
     * PaymentService checks it against what the client already paid.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'total' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'total.required' => 'Le montant total est obligatoire.',
            'total.numeric' => 'Le montant total doit être un nombre.',
            'total.min' => 'Le montant total doit être supérieur à 0.',
            'reason.required' => 'Indiquez pourquoi le montant change.',
            'reason.min' => 'Indiquez pourquoi le montant change.',
        ];
    }
}
