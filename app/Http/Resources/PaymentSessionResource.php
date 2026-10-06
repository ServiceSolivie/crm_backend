<?php

namespace App\Http\Resources;

use App\Enums\PaymentRefundStatusEnum;
use App\Enums\PaymentSessionStatusEnum;
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
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            // Paid, and no refund done or in progress (the permission is
            // checked separately: payments.refund)
            'refundable' => $this->status === PaymentSessionStatusEnum::PAYEE
                && (! $this->relationLoaded('latestRefund') || ! $this->latestRefund || $this->latestRefund->status === PaymentRefundStatusEnum::ECHOUE),
            'refund' => $this->whenLoaded('latestRefund', fn () => $this->latestRefund ? [
                'id' => $this->latestRefund->id,
                'status' => $this->latestRefund->status->value,
                'status_label' => $this->latestRefund->status->label(),
                'amount' => $this->latestRefund->amount,
                'reason' => $this->latestRefund->reason,
                'error_message' => $this->latestRefund->error_message,
                'refunded_at' => $this->formatDate($this->latestRefund->refunded_at),
                'created_at' => $this->formatDate($this->latestRefund->created_at),
            ] : null),
            'lead' => $this->whenLoaded('lead', fn () => $this->lead ? [
                'id' => $this->lead->id,
                'reference' => $this->lead->reference,
                'name' => trim($this->lead->first_name.' '.$this->lead->last_name),
                // Contract total, shown in the refund modal (it drops by the refunded amount)
                'contract_total' => $this->lead->expected_revenue,
            ] : null),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null),
            'created_at' => $this->formatDate($this->created_at),
        ];
    }
}
