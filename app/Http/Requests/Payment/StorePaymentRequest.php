<?php

namespace App\Http\Requests\Payment;

use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentRecordStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'min:0.01'],
            // Contract total, required with the first payment (PaymentService checks it)
            'expected_revenue' => ['nullable', 'numeric', 'min:0.01'],
            // By hand a payment is either received or still pending (bank transfer announced…)
            'status' => ['nullable', Rule::in([PaymentRecordStatusEnum::REUSSI->value, PaymentRecordStatusEnum::EN_ATTENTE->value])],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'payment_method' => ['required', Rule::in(PaymentMethodEnum::values())],
            'custom_payment_method' => ['required_if:payment_method,AUTRE', 'nullable', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Le montant est obligatoire.',
            'amount.min' => 'Le montant doit être supérieur à 0.',
            'expected_revenue.min' => 'Le montant total doit être supérieur à 0.',
            'payment_date.required' => 'La date de paiement est obligatoire.',
            'payment_date.before_or_equal' => 'La date de paiement ne peut pas être dans le futur.',
            'payment_method.required' => 'La méthode de paiement est obligatoire.',
            'custom_payment_method.required_if' => 'Veuillez préciser la méthode de paiement.',
        ];
    }
}
