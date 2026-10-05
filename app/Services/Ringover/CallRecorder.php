<?php

namespace App\Services\Ringover;

use App\Enums\CallDirectionEnum;
use App\Enums\CallStatusEnum;
use App\Models\Call;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a Call from a normalised payload (see RingoverCallMapper),
 * attributing it to the agent and matching it to a lead.
 *
 * Events can arrive late, twice or out of order, so every update only fills
 * in what is known and a status never moves backwards.
 */
class CallRecorder
{
    /** Fields copied as-is when present. Existing values are never erased with null. */
    protected const FIELDS = [
        'ringover_channel_id', 'direction', 'is_internal', 'from_number', 'to_number',
        'started_at', 'answered_at', 'ended_at', 'duration_seconds', 'talk_seconds',
        'recording_url', 'recording_duration_seconds', 'voicemail_url', 'transcription_url', 'ai_summary',
    ];

    public function record(array $data, string $source, ?string $event = null): Call
    {
        return DB::transaction(function () use ($data, $source, $event) {
            $call = Call::where('ringover_call_id', $data['ringover_call_id'])->lockForUpdate()->first()
                ?? new Call(['ringover_call_id' => $data['ringover_call_id'], 'source' => $source]);

            foreach (self::FIELDS as $field) {
                if (($data[$field] ?? null) !== null) {
                    $call->{$field} = $data[$field];
                }
            }

            $this->applyStatus($call, $data);
            $this->fillDerived($call);
            $this->attributeAgent($call, $data['ringover_user_id'] ?? null);
            $this->matchLead($call);

            $call->team_id ??= $call->agent?->team_id ?? $call->lead?->team_id;
            $call->last_event = $event ?? $call->last_event;
            $call->save();

            return $call;
        });
    }

    protected function applyStatus(Call $call, array $data): void
    {
        /** @var CallStatusEnum|null $current */
        $current = $call->status;
        $incoming = $data['status'] ?? null;

        if (! empty($data['ended']) && $incoming === null) {
            $incoming = match (true) {
                $call->answered_at !== null => CallStatusEnum::COMPLETED,
                $current === CallStatusEnum::VOICEMAIL => CallStatusEnum::VOICEMAIL,
                $call->direction === CallDirectionEnum::OUT => CallStatusEnum::NO_ANSWER,
                default => CallStatusEnum::MISSED,
            };
        }

        if ($incoming === null) {
            $call->status ??= CallStatusEnum::RINGING;

            return;
        }

        if ($current === null || $incoming->rank() > $current->rank() || $this->finalOverrides($current, $incoming)) {
            $call->status = $incoming;
        }
    }

    /**
     * Between two final statuses, a voicemail or an answered call is more
     * informative than a plain "missed / no answer".
     */
    protected function finalOverrides(CallStatusEnum $current, CallStatusEnum $incoming): bool
    {
        $weak = [CallStatusEnum::MISSED, CallStatusEnum::NO_ANSWER];

        return $current->isFinal() && $incoming->isFinal()
            && in_array($current, $weak, true)
            && in_array($incoming, [CallStatusEnum::VOICEMAIL, CallStatusEnum::COMPLETED], true);
    }

    protected function fillDerived(Call $call): void
    {
        if ($call->direction !== null) {
            $call->contact_number = $call->direction === CallDirectionEnum::OUT ? $call->to_number : $call->from_number;
        }

        if ($call->duration_seconds === null && $call->started_at && $call->ended_at) {
            $call->duration_seconds = max(0, (int) $call->started_at->diffInSeconds($call->ended_at));
        }

        if ($call->talk_seconds === null && $call->answered_at && $call->ended_at) {
            $call->talk_seconds = max(0, (int) $call->answered_at->diffInSeconds($call->ended_at));
        }
    }

    protected function attributeAgent(Call $call, ?string $ringoverUserId): void
    {
        if ($ringoverUserId === null) {
            return;
        }

        $userId = User::where('ringover_user_id', $ringoverUserId)->value('id');

        if ($userId !== null) {
            $call->user_id = $userId;
        }
    }

    /**
     * Find the lead by phone number. When several leads share the number,
     * prefer the one assigned to the agent on the call, then the most recently updated.
     */
    protected function matchLead(Call $call): void
    {
        if ($call->lead_id !== null || $call->is_internal || $call->contact_number === null) {
            return;
        }

        $query = Lead::where('phone_e164', $call->contact_number);

        if ($call->user_id !== null) {
            $query->orderByRaw('assigned_to = ? desc', [$call->user_id]);
        }

        $call->lead_id = $query->orderByDesc('updated_at')->value('id');
    }
}
