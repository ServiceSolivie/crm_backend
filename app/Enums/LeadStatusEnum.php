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

    /**
     * Statuses decided by the back office (gestion). Only users holding
     * leads.set_review_status may move a lead into one of them; agents hand
     * a lead over by setting GESTION instead.
     *
     * @return array<int, self>
     */
    public static function reviewStatuses(): array
    {
        return [
            self::VALIDE,
            self::CALL2_OK,
            self::CALL2_KO,
            self::PDG_OK,
            self::PDG_KO,
            self::A_CORRIGER,
        ];
    }

    public function isReviewStatus(): bool
    {
        return in_array($this, self::reviewStatuses(), true);
    }

    /**
     * Pipeline stage the status belongs to. Mirrors LEAD_STATUS in the
     * frontend's utils/enums.js — keep both in sync.
     */
    public function stage(): string
    {
        return match ($this) {
            self::NOUVEAU => 'new',
            self::PAS_DE_REPONSE, self::OCCUPE => 'contact',
            self::RAPPEL, self::EN_ATTENTE_CLIENT, self::A_CORRIGER, self::CALL2_KO, self::PDG_KO => 'follow_up',
            self::INTERESSE, self::DEVIS_EN_COURS, self::DEVIS_ENVOYE => 'quote',
            self::VALIDE, self::GESTION, self::CALL2_OK, self::PDG_OK => 'won',
            self::PERDU, self::PAS_INTERESSE, self::MAUVAIS_NUMERO, self::LEAD_INVALIDE => 'lost',
        };
    }

    /**
     * Status values in the given pipeline stage.
     *
     * @return array<int, string>
     */
    public static function valuesForStage(string $stage): array
    {
        return array_values(array_map(
            fn (self $s) => $s->value,
            array_filter(self::cases(), fn (self $s) => $s->stage() === $stage),
        ));
    }

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
