<?php

namespace App\Contracts\Templates;

use App\Models\Lead;
use App\Models\User;

/**
 * "Devis et Conseil" (fiche d'informations et de conseils) for décennale
 * (RC Décennale construction) insurance — Devoir de Conseil document for
 * MOOV ASSUR, same drafting as AutoDvcTemplate but for a professional
 * (company) subscriber instead of an individual driver.
 */
class DecennaleDvcTemplate extends ContractTemplate
{
    public function key(): string
    {
        return 'decennale_dvc';
    }

    public function label(): string
    {
        return 'Devoir de Conseil — Assurance Décennale';
    }

    public function view(): string
    {
        return 'contracts.decennale-dvc';
    }

    public function filenamePrefix(): string
    {
        return 'DVC-DECENNALE';
    }

    public function schema(): array
    {
        return [
            [
                'key' => 'conseiller',
                'title' => 'Votre conseiller',
                'type' => 'fields',
                'fields' => [
                    ['key' => 'agent_name', 'label' => 'Nom du conseiller', 'type' => 'text'],
                    ['key' => 'agent_phone', 'label' => 'Ligne directe', 'type' => 'text'],
                    ['key' => 'agent_email', 'label' => 'Adresse mail', 'type' => 'text'],
                    ['key' => 'interview_date', 'label' => "Date de l'entretien", 'type' => 'date'],
                ],
            ],
            [
                'key' => 'souscripteur',
                'title' => "L'entreprise souscriptrice",
                'type' => 'fields',
                'fields' => [
                    ['key' => 'civilite', 'label' => 'Civilité du dirigeant', 'type' => 'select', 'options' => ['M.', 'Mme', 'Mlle']],
                    ['key' => 'first_name', 'label' => 'Prénom du dirigeant', 'type' => 'text'],
                    ['key' => 'last_name', 'label' => 'Nom du dirigeant', 'type' => 'text'],
                    ['key' => 'phone', 'label' => 'Numéro de téléphone', 'type' => 'text'],
                    ['key' => 'email', 'label' => 'Adresse mail', 'type' => 'text'],
                    ['key' => 'address', 'label' => 'Adresse du siège social', 'type' => 'text'],
                    ['key' => 'company_name', 'label' => 'Raison sociale', 'type' => 'text'],
                    ['key' => 'company_legal_form', 'label' => 'Forme juridique', 'type' => 'text'],
                    ['key' => 'siret', 'label' => 'SIREN / SIRET', 'type' => 'text'],
                    ['key' => 'company_creation_date', 'label' => "Date de création de l'entreprise", 'type' => 'date'],
                    ['key' => 'company_employee_count', 'label' => 'Nombre de salariés', 'type' => 'text'],
                    ['key' => 'company_annual_revenue', 'label' => "Chiffre d'affaires annuel N-1 (€)", 'type' => 'number'],
                    ['key' => 'birth_date', 'label' => 'Date de naissance du dirigeant', 'type' => 'date'],
                ],
            ],
            [
                'key' => 'activite',
                'title' => "L'activité",
                'type' => 'fields',
                'fields' => [
                    ['key' => 'company_sector', 'label' => "Secteur d'activité", 'type' => 'text'],
                    ['key' => 'activity_description', 'label' => 'Nature des travaux réalisés', 'type' => 'textarea'],
                    ['key' => 'qualifications', 'label' => 'Qualifications professionnelles (Qualibat, RGE, ...)', 'type' => 'text'],
                    ['key' => 'diploma_experience', 'label' => "Diplôme / formation du dirigeant dans l'activité", 'type' => 'text'],
                    ['key' => 'experience_years', 'label' => "Années d'expérience dans l'activité", 'type' => 'text'],
                    ['key' => 'subcontracting_share', 'label' => 'Part de sous-traitance (%)', 'type' => 'text'],
                    ['key' => 'intervention_area', 'label' => "Zone géographique d'intervention", 'type' => 'text'],
                ],
            ],
            [
                'key' => 'antecedents',
                'title' => 'Les antécédents',
                'type' => 'fields',
                'fields' => [
                    ['key' => 'previously_insured', 'label' => 'Déjà assuré pour ce risque', 'type' => 'select', 'options' => ['Oui', 'Non']],
                    ['key' => 'termination_reason', 'label' => 'Motif de résiliation antérieure', 'type' => 'text'],
                    ['key' => 'terminated_non_payment', 'label' => 'Résilié pour non-paiement', 'type' => 'select', 'options' => ['Oui', 'Non']],
                    ['key' => 'terminated_false_declaration', 'label' => 'Résilié pour fausse déclaration', 'type' => 'select', 'options' => ['Oui', 'Non']],
                    ['key' => 'judicial_receivership', 'label' => 'Entreprise en redressement judiciaire', 'type' => 'select', 'options' => ['Oui', 'Non']],
                    ['key' => 'claims_5y_count', 'label' => 'Sinistres déclarés sur les 5 dernières années', 'type' => 'text'],
                    ['key' => 'claims_5y_amount', 'label' => 'Montant total des sinistres (€)', 'type' => 'number'],
                    ['key' => 'claims_5y_detail', 'label' => 'Détail des sinistres', 'type' => 'textarea'],
                ],
            ],
            [
                'key' => 'garanties',
                'title' => 'Vos exigences et besoins',
                'type' => 'garanties',
                'items' => [
                    ['key' => 'g_rc_decennale', 'label' => 'Responsabilité civile décennale (RCD)'],
                    ['key' => 'g_rc_pro', 'label' => 'Responsabilité civile professionnelle'],
                    ['key' => 'g_rc_exploitation', 'label' => "Responsabilité civile d'exploitation"],
                    ['key' => 'g_bon_fonctionnement', 'label' => 'Garantie de bon fonctionnement (2 ans)'],
                    ['key' => 'g_apres_travaux', 'label' => 'Garantie après travaux'],
                    ['key' => 'g_defense', 'label' => 'Défense pénale et recours'],
                    ['key' => 'g_gap', 'label' => 'Garantie accident de la vie professionnelle (GAP)'],
                    ['key' => 'g_dommages_ouvrage', 'label' => 'Dommages-ouvrage (si maître d\'ouvrage)'],
                ],
            ],
            [
                'key' => 'tarification',
                'title' => 'Recommandation et tarification',
                'type' => 'fields',
                'fields' => [
                    ['key' => 'formule', 'label' => 'Formule conseillée', 'type' => 'text'],
                    ['key' => 'compagnie', 'label' => 'Compagnie partenaire', 'type' => 'text'],
                    ['key' => 'effective_date', 'label' => "Date d'effet souhaitée", 'type' => 'date'],
                    ['key' => 'echeancier', 'label' => 'Échéancier', 'type' => 'select', 'options' => ['Annuel', 'Semestriel', 'Trimestriel']],
                    ['key' => 'prime_annuelle', 'label' => 'Prime annuelle (€ TTC)', 'type' => 'number'],
                    ['key' => 'prime_mensuelle', 'label' => 'Prime mensuelle (€ TTC)', 'type' => 'number'],
                    ['key' => 'frais_courtage', 'label' => 'Frais de courtage (€)', 'type' => 'number'],
                ],
            ],
            [
                'key' => 'paiement',
                'title' => 'Paiement',
                'type' => 'fields',
                'fields' => [
                    ['key' => 'debit_amount', 'label' => 'Montant autorisé au débit (€)', 'type' => 'number'],
                    ['key' => 'installment_1_amount', 'label' => 'Échéance 1 — montant (€)', 'type' => 'number'],
                    ['key' => 'installment_1_date', 'label' => 'Échéance 1 — date', 'type' => 'date'],
                    ['key' => 'installment_2_amount', 'label' => 'Échéance 2 — montant (€)', 'type' => 'number'],
                    ['key' => 'installment_2_date', 'label' => 'Échéance 2 — date', 'type' => 'date'],
                    ['key' => 'payment_mode', 'label' => 'Mode de paiement', 'type' => 'text'],
                    ['key' => 'payment_platform', 'label' => 'Plateforme de paiement', 'type' => 'text'],
                ],
            ],
        ];
    }

    public function prefill(?Lead $lead, User $agent): array
    {
        $values = [
            'agent_name' => $agent->name,
            'agent_email' => $agent->email,
            'agent_phone' => $agent->phone ?? config('contracts.broker.phone'),
            'interview_date' => now()->toDateString(),
            'payment_mode' => 'Lien de paiement envoyé par mail',
            'g_rc_decennale_included' => true,
        ];

        if ($lead) {
            $assignedAgent = $lead->assignedAgent;

            $values = array_merge($values, array_filter([
                'first_name' => $lead->first_name,
                'last_name' => $lead->last_name,
                'phone' => $lead->phone,
                'email' => $lead->email,
                'address' => $lead->address,
                'company_name' => $lead->company_name,
                'company_legal_form' => $lead->company_legal_form,
                'company_sector' => $lead->company_sector,
                'company_employee_count' => $lead->company_employee_count,
                'company_annual_revenue' => $lead->company_annual_revenue,
            ], fn ($value) => $value !== null && $value !== ''));

            if ($assignedAgent) {
                $values['agent_name'] = $assignedAgent->name;
                $values['agent_email'] = $assignedAgent->email;
                $values['agent_phone'] = $assignedAgent->phone ?? config('contracts.broker.phone');
            }
        }

        return $values;
    }
}
