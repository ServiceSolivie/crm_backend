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
    case GESTION = 'GESTION';
    case A_CORRIGER = 'A_CORRIGER';
    case CALL2_OK = 'CALL2_OK';
    case CALL2_KO = 'CALL2_KO';
    case PDG_OK = 'PDG_OK';
    case PDG_KO = 'PDG_KO';

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
            self::GESTION => 'Gestion',
            self::A_CORRIGER => 'À corriger',
            self::CALL2_OK => 'Call2 OK',
            self::CALL2_KO => 'Call2 KO',
            self::PDG_OK => 'PDG OK',
            self::PDG_KO => 'PDG KO',
        };
    }
}
