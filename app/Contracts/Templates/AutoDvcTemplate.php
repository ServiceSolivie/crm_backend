<?php

namespace App\Contracts\Templates;

use App\Models\Lead;
use App\Models\User;

/**
 * "Devis et Conseil" (fiche d'informations et de conseils) for auto
 * insurance — the redesigned version of the original SOLIVIE DVC document.
 */
class AutoDvcTemplate extends ContractTemplate
{
    public function key(): string
    {
        return 'auto_dvc';
    }

    public function label(): string
    {
        return 'Devoir de Conseil — Assurance Auto';
    }

    public function view(): string
    {
        return 'contracts.auto-dvc';
    }

    public function filenamePrefix(): string
    {
        return 'DVC';
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
                'title' => 'Le souscripteur ou conducteur principal',
                'type' => 'fields',
                'fields' => [
                    ['key' => 'civilite', 'label' => 'Civilité', 'type' => 'select', 'options' => ['M.', 'Mme', 'Mlle']],
                    ['key' => 'first_name', 'label' => 'Prénom', 'type' => 'text'],
                    ['key' => 'last_name', 'label' => 'Nom', 'type' => 'text'],
                    ['key' => 'birth_date', 'label' => 'Date de naissance', 'type' => 'date'],
                    ['key' => 'license_date', 'label' => "Date d'obtention du permis", 'type' => 'date'],
                    ['key' => 'phone', 'label' => 'Numéro de téléphone', 'type' => 'text'],
                    ['key' => 'email', 'label' => 'Adresse mail', 'type' => 'text'],
                    ['key' => 'address', 'label' => 'Adresse postale', 'type' => 'text'],
                    ['key' => 'siret', 'label' => 'SIREN / SIRET', 'type' => 'text'],
                    ['key' => 'company_name', 'label' => 'Raison sociale', 'type' => 'text'],
                ],
            ],
            [
                'key' => 'antecedents_assurance',
                'title' => "Les antécédents d'assurance",
                'type' => 'fields',
                'fields' => [
                    ['key' => 'bonus_malus', 'label' => 'Coefficient bonus-malus', 'type' => 'text'],
                    ['key' => 'months_insured', 'label' => "Mois d'assurance sur les 36 derniers mois", 'type' => 'text'],
                    ['key' => 'termination_reason', 'label' => 'Motif de résiliation', 'type' => 'text'],
                    ['key' => 'claims_material_resp', 'label' => 'Sinistres matériels responsables', 'type' => 'text'],
                    ['key' => 'claims_material_nonresp', 'label' => 'Sinistres matériels non responsables', 'type' => 'text'],
                    ['key' => 'claims_bodily_resp', 'label' => 'Sinistres corporels responsables', 'type' => 'text'],
                    ['key' => 'claims_bodily_nonresp', 'label' => 'Sinistres corporels non responsables', 'type' => 'text'],
                    ['key' => 'glass_breakage', 'label' => 'Bris de glace', 'type' => 'text'],
                    ['key' => 'theft_count', 'label' => 'Vols', 'type' => 'text'],
                ],
            ],
            [
                'key' => 'antecedents_souscripteur',
                'title' => 'Les antécédents du souscripteur',
                'type' => 'fields',
                'fields' => [
                    ['key' => 'suspension_alcohol', 'label' => 'Suspension de permis suite à alcoolémie', 'type' => 'text'],
                    ['key' => 'suspension_drugs', 'label' => 'Suspension suite à usage de stupéfiants', 'type' => 'text'],
                    ['key' => 'suspension_points', 'label' => 'Suspension pour perte de points', 'type' => 'text'],
                    ['key' => 'cancellation_points', 'label' => 'Annulation pour perte de points', 'type' => 'text'],
                    ['key' => 'suspension_months', 'label' => 'Nombre de mois de suspension', 'type' => 'text'],
                ],
            ],
            [
                'key' => 'vehicule',
                'title' => 'Le véhicule',
                'type' => 'fields',
                'fields' => [
                    ['key' => 'vehicle_brand', 'label' => 'Marque', 'type' => 'text'],
                    ['key' => 'vehicle_model', 'label' => 'Modèle', 'type' => 'text'],
                    ['key' => 'vehicle_first_registration', 'label' => '1ʳᵉ mise en circulation', 'type' => 'date'],
                    ['key' => 'vehicle_purchase_date', 'label' => "Date d'achat", 'type' => 'date'],
                    ['key' => 'vehicle_plate', 'label' => 'Immatriculation', 'type' => 'text'],
                    ['key' => 'vehicle_usage', 'label' => 'Usage', 'type' => 'text'],
                ],
            ],
            [
                'key' => 'garanties',
                'title' => 'Vos exigences et besoins',
                'type' => 'garanties',
                'items' => [
                    ['key' => 'g_rc', 'label' => 'Responsabilité civile'],
                    ['key' => 'g_defense', 'label' => 'Défense pénale et recours'],
                    ['key' => 'g_rc_pro', 'label' => 'Responsabilité civile professionnelle'],
                    ['key' => 'g_conducteur', 'label' => 'Garantie conducteur'],
                    ['key' => 'g_assistance', 'label' => 'Assistance'],
                    ['key' => 'g_bris_glace', 'label' => 'Bris de glace'],
                    ['key' => 'g_vol_incendie', 'label' => 'Vol - Incendie'],
                    ['key' => 'g_cat_nat', 'label' => 'Catastrophes naturelles'],
                    ['key' => 'g_cat_tech', 'label' => 'Catastrophes technologiques'],
                    ['key' => 'g_attentats', 'label' => 'Attentats'],
                    ['key' => 'g_dommages', 'label' => 'Dommages tous accidents'],
                    ['key' => 'g_vehicule_remplacement', 'label' => 'Véhicule de remplacement'],
                ],
            ],
            [
                'key' => 'tarification',
                'title' => 'Recommandation et tarification',
                'type' => 'fields',
                'fields' => [
                    ['key' => 'formule', 'label' => 'Formule conseillée', 'type' => 'text'],
                    ['key' => 'compagnie', 'label' => 'Compagnie partenaire', 'type' => 'text'],
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
            'g_rc_included' => true,
        ];

        if ($lead) {
            $assignedAgent = $lead->assignedAgent;

            $values = array_merge($values, array_filter([
                'first_name' => $lead->first_name,
                'last_name' => $lead->last_name,
                'birth_date' => $lead->birth_date?->toDateString(),
                'phone' => $lead->phone,
                'email' => $lead->email,
                'address' => trim(implode(' ', array_filter([$lead->address, $lead->city]))),
                'siret' => null,
                'company_name' => $lead->company_name,
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
