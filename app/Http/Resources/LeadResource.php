<?php

namespace App\Http\Resources;

use App\Enums\LeadStatusEnum;
use Illuminate\Http\Request;

class LeadResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'city' => $this->city,
            'address' => $this->address,
            'birth_date' => $this->birth_date?->toDateString(),
            'insurance_type' => $this->insurance_type?->value,
            'insurance_type_label' => $this->insurance_type?->label(),
            'client_type' => $this->client_type?->value,
            'client_type_label' => $this->client_type?->label(),
            'company_status' => $this->company_status,
            'company_legal_form' => $this->company_legal_form,
            'company_sector' => $this->company_sector,
            'company_employee_count' => $this->company_employee_count,
            'company_name' => $this->company_name,
            'company_annual_revenue' => $this->company_annual_revenue,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'comment' => $this->comment,
            'is_doublon' => $this->is_doublon,
            'doublon_of' => $this->whenLoaded('doublonOf', fn () => $this->doublonOf ? [
                'id' => $this->doublonOf->id,
                'reference' => $this->doublonOf->reference,
            ] : null),
            'lead_submitted_at' => $this->formatDate($this->lead_submitted_at),
            'lead_source' => $this->whenLoaded('leadSource', fn () => [
                'id' => $this->leadSource->id,
                'name' => $this->leadSource->name,
                'code' => $this->leadSource->code,
            ]),
            'assigned_agent' => $this->whenLoaded('assignedAgent', fn () => $this->assignedAgent ? [
                'id' => $this->assignedAgent->id,
                'name' => $this->assignedAgent->name,
                'email' => $this->assignedAgent->email,
            ] : null),
            'team' => $this->whenLoaded('team', fn () => $this->team ? [
                'id' => $this->team->id,
                'name' => $this->team->name,
            ] : null),
            'expected_revenue' => $this->expected_revenue,
            'total_received' => $this->when($this->expected_revenue !== null, fn () => $this->total_received),
            'remaining_amount' => $this->when($this->expected_revenue !== null, fn () => $this->remaining_amount),
            'payment_status' => $this->payment_status?->value,
            'payment_status_label' => $this->payment_status?->label(),
            'validated_at' => $this->formatDate($this->validated_at),
            'dvc_status' => $this->dvc_status?->value,
            'dvc_status_label' => $this->dvc_status?->label(),
            'dvc_signed_at' => $this->formatDate($this->dvc_signed_at),
            'payments_count' => $this->whenCounted('payments'),
            'calls_count' => $this->whenCounted('calls'),
            'next_action' => $this->when(
                $this->relationLoaded('nextAppointment'),
                fn () => $this->nextAction(),
            ),
            'last_flag' => $this->whenLoaded('lastFlag', fn () => $this->lastFlag ? [
                'message' => $this->lastFlag->meta['comment'] ?? $this->lastFlag->comment,
                'issue_types' => $this->lastFlag->meta['issue_types'] ?? [],
                'documents' => $this->lastFlag->meta['missing_documents'] ?? [],
                'summary' => $this->lastFlag->comment,
                'by' => $this->lastFlag->changedBy ? [
                    'id' => $this->lastFlag->changedBy->id,
                    'name' => $this->lastFlag->changedBy->name,
                ] : null,
                'at' => $this->formatDate($this->lastFlag->created_at),
            ] : null),
            'created_by' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'created_at' => $this->formatDate($this->created_at),
            'updated_at' => $this->formatDate($this->updated_at),
        ];
    }

    /**
     * What the agent has to do next on this lead: the earliest open
     * appointment, or fixing the dossier when gestion sent it back.
     *
     * @return array<string, mixed>|null
     */
    protected function nextAction(): ?array
    {
        if ($appointment = $this->nextAppointment) {
            return [
                'type' => 'appointment',
                'id' => $appointment->id,
                'at' => $this->formatDate($appointment->scheduled_at),
                'overdue' => $appointment->scheduled_at->isPast(),
            ];
        }

        if ($this->status === LeadStatusEnum::A_CORRIGER) {
            return [
                'type' => 'missing_document',
                'id' => null,
                'at' => null,
                'overdue' => false,
            ];
        }

        return null;
    }
}
