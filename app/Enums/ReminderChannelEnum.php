<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasLabel;

enum ReminderChannelEnum: string implements HasLabel
{
    use EnumHelpers;

    case IN_APP = 'in_app';
    case EMAIL = 'email';
    case SMS = 'sms';

    public function label(): string
    {
        return match ($this) {
            self::IN_APP => 'In-App',
            self::EMAIL => 'Email',
            self::SMS => 'SMS',
        };
    }
}
