<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class CallResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canListen = (bool) $request->user()?->can('listen', $this->resource);

        return [
            'id' => $this->id,
            'direction' => $this->direction?->value,
            'direction_label' => $this->direction?->label(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'is_internal' => $this->is_internal,
            'from_number' => $this->from_number,
            'to_number' => $this->to_number,
            'contact_number' => $this->contact_number,
            'lead' => $this->whenLoaded('lead', fn () => $this->lead ? [
                'id' => $this->lead->id,
                'reference' => $this->lead->reference,
                'name' => trim($this->lead->first_name.' '.$this->lead->last_name),
            ] : null),
            'agent' => $this->whenLoaded('agent', fn () => $this->agent ? [
                'id' => $this->agent->id,
                'name' => $this->agent->name,
            ] : null),
            'started_at' => $this->formatDate($this->started_at),
            'answered_at' => $this->formatDate($this->answered_at),
            'ended_at' => $this->formatDate($this->ended_at),
            'duration_seconds' => $this->duration_seconds,
            'talk_seconds' => $this->talk_seconds,
            'has_recording' => $this->recording_url !== null,
            'has_voicemail' => $this->voicemail_url !== null,
            'has_transcription' => $this->transcription_url !== null,
            'has_summary' => $this->ai_summary !== null,
            'can_listen' => $canListen,
            // The AI summary is as sensitive as the recording.
            'ai_summary' => $canListen ? $this->ai_summary : null,
            'note' => $this->note,
            'source' => $this->source,
            'created_at' => $this->formatDate($this->created_at),
        ];
    }
}
