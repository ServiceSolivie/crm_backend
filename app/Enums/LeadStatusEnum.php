<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasLabel;

enum LeadStatusEnum: string implements HasLabel
{
    use EnumHelpers;

    case NOUVEAU = 'NOUVEAU';
    case PAS_DE_REPONSE = 'PAS_DE_REPONSE';
    case OCCUPE = 'OCCUPE';
    case RAPPEL = 'RAPPEL';
    case INTERESSE = 'INTERESSE';
    case DEVIS_EN_COURS = 'DEVIS_EN_COURS';
    case DEVIS_ENVOYE = 'DEVIS_ENVOYE';
    case EN_ATTENTE_CLIENT = 'EN_ATTENTE_CLIENT';
    case VALIDE = 'VALIDE';
    case PERDU = 'PERDU';
    case PAS_INTERESSE = 'PAS_INTERESSE';
    case MAUVAIS_NUMERO = 'MAUVAIS_NUMERO';
    case LEAD_INVALIDE = 'LEAD_INVALIDE';

    public function label(): string
    {
        return match ($this) {
            self::NOUVEAU => 'Nouveau',
            self::PAS_DE_REPONSE => 'Pas de réponse',
            self::OCCUPE => 'Occupé',
            self::RAPPEL => 'Rappel',
            self::INTERESSE => 'Intéressé',
            self::DEVIS_EN_COURS => 'Devis en cours',
            self::DEVIS_ENVOYE => 'Devis envoyé',
            self::EN_ATTENTE_CLIENT => 'En attente du client',
            self::VALIDE => 'Validé',
            self::PERDU => 'Perdu',
            self::PAS_INTERESSE => 'Pas intéressé',
            self::MAUVAIS_NUMERO => 'Mauvais numéro',
            self::LEAD_INVALIDE => 'Lead invalide',
        };
    }
}
