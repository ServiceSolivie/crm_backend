<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasLabel;

enum AuditActionEnum: string implements HasLabel
{
    use EnumHelpers;

    case CREATED = 'created';
    case UPDATED = 'updated';
    case DELETED = 'deleted';
    case RESTORED = 'restored';
    case STATUS_CHANGED = 'status_changed';
    case ASSIGNED = 'assigned';

    public function label(): string
    {
        return match ($this) {
            self::CREATED => 'Created',
            self::UPDATED => 'Updated',
            self::DELETED => 'Deleted',
            self::RESTORED => 'Restored',
            self::STATUS_CHANGED => 'Status Changed',
            self::ASSIGNED => 'Assigned',
        };
    }
}
