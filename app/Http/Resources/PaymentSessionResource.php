<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class PaymentSessionResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'payment_url' => $this->payment_url,
            'client_email' => $this->client_email,
            'hyperswitch_payment_id' => $this->hyperswitch_payment_id,
            'sent_at' => $this->formatDate($this->sent_at),
            'paid_at' => $this->formatDate($this->paid_at),
            // Bank refusal (support): Sogecommerce code and message
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'lead' => $this->whenLoaded('lead', fn () => $this->lead ? [
                'id' => $this->lead->id,
                'reference' => $this->lead->reference,
                'name' => trim($this->lead->first_name.' '.$this->lead->last_name),
            ] : null),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null),
            'created_at' => $this->formatDate($this->created_at),
        ];
    }
}
