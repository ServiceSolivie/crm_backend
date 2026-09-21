<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasLabel;

/**
 * Where a lead's DVC stands. Computed by LeadDvcStatus, never set by hand:
 * generating a DVC (contract) → EN_ATTENTE_SIGNATURE, uploading the signed
 * copy as a "DVC" document → SIGNE.
 */
enum DvcStatusEnum: string implements HasLabel
{
    use EnumHelpers;

    case A_GENERER = 'A_GENERER';
    case EN_ATTENTE_SIGNATURE = 'EN_ATTENTE_SIGNATURE';
    case SIGNE = 'SIGNE';

    public function label(): string
    {
        return match ($this) {
            self::A_GENERER => 'DVC à générer',
            self::EN_ATTENTE_SIGNATURE => 'En attente de signature',
            self::SIGNE => 'DVC signé',
        };
    }
}
