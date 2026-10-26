<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasLabel;

enum CallStatusEnum: string implements HasLabel
{
    use EnumHelpers;

    case INITIATED = 'initiated';
    case RINGING = 'ringing';
    case ANSWERED = 'answered';
    case COMPLETED = 'completed';
    case MISSED = 'missed';
    case NO_ANSWER = 'no_answer';
    case VOICEMAIL = 'voicemail';

    public function label(): string
    {
        return match ($this) {
            self::INITIATED => 'Initié',
            self::RINGING => 'Sonne',
            self::ANSWERED => 'En cours',
            self::COMPLETED => 'Terminé',
            self::MISSED => 'Manqué',
            self::NO_ANSWER => 'Pas de réponse',
            self::VOICEMAIL => 'Messagerie',
        };
    }

    /**
     * A call has ended once it reaches one of these statuses.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::COMPLETED, self::MISSED, self::NO_ANSWER, self::VOICEMAIL], true);
    }

    /**
     * Events can arrive out of order; a status may only move forward.
     */
    public function rank(): int
    {
        return match ($this) {
            self::INITIATED => 0,
            self::RINGING => 1,
            self::ANSWERED => 2,
            default => 3,
        };
    }
}
