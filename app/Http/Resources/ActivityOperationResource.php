<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ActivityOperationResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind(),
            'category' => $this->category->value,
            'category_label' => $this->category->label(),
            'has_refund' => $this->has_refund,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'reference' => $this->reference,
            'state' => $this->state->value,
            'state_label' => $this->state_label ?? $this->state->label(),
            'state_event' => $this->state_event,
            'logs_count' => $this->logs_count,
            'problems_count' => $this->problems_count,
            // A warning or a failure somewhere in it, or standing in such a state
            'has_problem' => $this->problems_count > 0 || $this->state->isProblem(),
            'lead' => $this->whenLoaded('lead', fn () => $this->lead ? [
                'id' => $this->lead->id,
                'reference' => $this->lead->reference,
                'name' => trim($this->lead->first_name.' '.$this->lead->last_name),
            ] : null),
            'logs' => ActivityLogResource::collection($this->whenLoaded('logs')),
            'started_at' => $this->formatDate($this->started_at),
            'last_activity_at' => $this->formatDate($this->last_activity_at),
        ];
    }
}
