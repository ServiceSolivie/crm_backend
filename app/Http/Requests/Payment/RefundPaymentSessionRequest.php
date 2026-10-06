<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class RefundPaymentSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * No amount: a session is always refunded in full (Sogecommerce refuses
     * partial refunds). The reason is kept on the refund and in the lead note.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Le motif du remboursement est obligatoire.',
            'reason.min' => 'Le motif du remboursement est trop court.',
        ];
    }
}
