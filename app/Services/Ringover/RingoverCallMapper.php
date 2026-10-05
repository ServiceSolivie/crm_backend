<?php

namespace App\Services\Ringover;

use App\Enums\CallDirectionEnum;
use App\Enums\CallStatusEnum;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Translates Ringover payloads (webhooks and the calls API) into one
 * normalised array understood by CallRecorder.
 *
 * Keys (all optional except ringover_call_id): ringover_call_id, ringover_channel_id,
 * direction, status, ended, from_number, to_number, ringover_user_id, is_internal,
 * started_at, answered_at, ended_at, duration_seconds, talk_seconds, recording_url,
 * recording_duration_seconds, voicemail_url, transcription_url, ai_summary.
 *
 * "ended" => true means "the call hung up"; the recorder works out whether it
 * was completed, missed or went to voicemail from what it already knows.
 */
class RingoverCallMapper
{
    /** Webhook events that change call data. Others (tags, comments, IVR, …) are ignored. */
    public const HANDLED_EVENTS = [
        'ringing', 'answered', 'hangup', 'missed', 'voicemail',
        'record_available', 'voicemail_available', 'transcription_available', 'summary_available',
    ];

    public function fromWebhook(string $event, array $data): ?array
    {
        if (! in_array($event, self::HANDLED_EVENTS, true) || blank($data['call_id'] ?? null)) {
            return null;
        }

        $call = [
            'ringover_call_id' => (string) $data['call_id'],
            'ringover_channel_id' => $this->string($data['channel_id'] ?? null),
        ];

        if (in_array($event, ['ringing', 'answered', 'hangup', 'missed', 'voicemail'], true)) {
            $call += [
                'direction' => CallDirectionEnum::fromRingover($data['direction'] ?? null),
                'from_number' => PhoneNumber::fromRingover($data['from_number'] ?? null),
                'to_number' => PhoneNumber::fromRingover($data['to_number'] ?? null),
                'ringover_user_id' => $this->string($data['user_id'] ?? $data['user']['user_id'] ?? $data['user']['userId'] ?? null),
                'is_internal' => isset($data['is_internal']) ? (bool) $data['is_internal'] : null,
                'started_at' => $this->time($data['start_date_time_atom'] ?? $data['start_time'] ?? null),
                'answered_at' => $this->time($data['answered_date_time_atom'] ?? $data['answered_time'] ?? null),
            ];
        }

        return array_merge($call, match ($event) {
            'ringing' => ['status' => CallStatusEnum::RINGING],
            'answered' => ['status' => CallStatusEnum::ANSWERED],
            'hangup' => [
                'ended' => true,
                'ended_at' => $this->time($data['hangup_date_time_atom'] ?? $data['hangup_time'] ?? null),
                'duration_seconds' => $this->int($data['duration_in_seconds'] ?? null),
                'recording_url' => $this->string($data['record'] ?? $data['private_record'] ?? null),
            ],
            'missed' => [
                'ended' => true,
                'ended_at' => $this->time($data['hangup_date_time_atom'] ?? $data['hangup_time'] ?? null),
            ],
            'voicemail' => [
                'status' => CallStatusEnum::VOICEMAIL,
                'ended_at' => $this->time($data['hangup_date_time_atom'] ?? $data['hangup_time'] ?? null),
                'voicemail_url' => $this->string($data['message'] ?? $data['private_message'] ?? null),
            ],
            'record_available' => [
                'recording_url' => $this->string($data['record_link'] ?? $data['private_record_link'] ?? null),
                'recording_duration_seconds' => $this->int($data['record_duration'] ?? null),
            ],
            'voicemail_available' => [
                'status' => CallStatusEnum::VOICEMAIL,
                'voicemail_url' => $this->string($data['voicemail_link'] ?? $data['private_voicemail_link'] ?? null),
            ],
            'transcription_available' => [
                'transcription_url' => $this->string($data['transcription_link'] ?? null),
            ],
            'summary_available' => [
                'ai_summary' => $this->summary($data['summary'] ?? null),
            ],
        });
    }

    /**
     * One entry of GET /v2/calls ("call_list").
     */
    public function fromCallsApi(array $item): ?array
    {
        if (blank($item['call_id'] ?? null)) {
            return null;
        }

        $answeredAt = $this->time($item['answered_time'] ?? null);
        $state = strtoupper((string) ($item['last_state'] ?? ''));

        $call = [
            'ringover_call_id' => (string) $item['call_id'],
            'direction' => CallDirectionEnum::fromRingover($item['direction'] ?? null),
            'from_number' => PhoneNumber::fromRingover($item['from_number'] ?? null),
            'to_number' => PhoneNumber::fromRingover($item['to_number'] ?? null),
            'ringover_user_id' => $this->string($item['user']['user_id'] ?? null),
            'started_at' => $this->time($item['start_time'] ?? null),
            'answered_at' => $answeredAt,
            'ended_at' => $this->time($item['end_time'] ?? null),
            'duration_seconds' => $this->int($item['total_duration'] ?? null),
            'talk_seconds' => $this->int($item['incall_duration'] ?? null),
            'recording_url' => $this->string($item['record'] ?? null),
            'voicemail_url' => $this->string($item['voicemail'] ?? null),
        ];

        if ($state === 'VOICEMAIL') {
            $call['status'] = CallStatusEnum::VOICEMAIL;
        } elseif ($state === 'ANSWERED' || $answeredAt) {
            $call['status'] = CallStatusEnum::COMPLETED;
        } elseif ($call['ended_at'] || $state !== '') {
            $call['ended'] = true;
        }

        return $call;
    }

    protected function time(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return null;
        }

        try {
            $time = is_numeric($value)
                ? CarbonImmutable::createFromTimestamp((int) $value)
                : CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }

        return $time->setTimezone(config('app.timezone'));
    }

    protected function int(mixed $value): ?int
    {
        return is_numeric($value) ? max(0, (int) $value) : null;
    }

    protected function string(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }

    /**
     * The summary may come as plain text or as structured content.
     */
    protected function summary(mixed $value): ?string
    {
        if (is_array($value)) {
            return $value['text'] ?? $value['content'] ?? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        return $this->string($value);
    }
}
