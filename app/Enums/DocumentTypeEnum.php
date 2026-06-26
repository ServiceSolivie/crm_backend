<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasLabel;

enum DocumentTypeEnum: string implements HasLabel
{
    use EnumHelpers;

    case CARTE_IDENTITE = 'CARTE_IDENTITE';
    case PERMIS_CONDUIRE = 'PERMIS_CONDUIRE';
    case CARTE_GRISE = 'CARTE_GRISE';
    case RIB = 'RIB';
    case RELEVE_INFORMATION = 'RELEVE_INFORMATION';
    case EXTRAIT_KBIS = 'EXTRAIT_KBIS';
    case NUMERO_SIRET = 'NUMERO_SIRET';
    case CONTRAT_CHANTIER = 'CONTRAT_CHANTIER';

    public function label(): string
    {
        return match ($this) {
            self::CARTE_IDENTITE => 'Carte d\'identité',
            self::PERMIS_CONDUIRE => 'Permis de conduire',
            self::CARTE_GRISE => 'Carte grise',
            self::RIB => 'RIB',
            self::RELEVE_INFORMATION => 'Relevé d\'information',
            self::EXTRAIT_KBIS => 'Extrait Kbis',
            self::NUMERO_SIRET => 'Numéro SIRET',
            self::CONTRAT_CHANTIER => 'Contrat Chantier',
        };
    }
}
