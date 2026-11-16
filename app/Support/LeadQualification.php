<?php

namespace App\Support;

use App\Models\Lead;

/**
 * The answers a lead gave on the form (« Détaillés » tab, Décennale tab,
 * webhook extras), read from Lead::comment and the company columns into
 * one clean shape for the super admin's leads list:
 *
 *   insured: { code: yes|no|cancelled, label }
 *   reason:  { label }
 *   timing:  { code: now|week|month|comparing, label }
 *   company: { name, sector, seniority, status }
 *   extra:   [ { key, label, value } ]
 *
 * The same question arrives under several keys depending on the sheet and
 * its age, and values are form slugs (prix_trop_cher): both are folded
 * here. Null when the lead has nothing to show.
 */
class LeadQualification
{
    /** Keys each question arrives under */
    private const ALIASES = [
        'insured' => ['currently_insured', 'assure_actuellement', 'etes-vous_actuellement_assure', 'etes_vous_actuellement_assure'],
        'reason' => ['switch_reason', 'raison_changement', 'pourquoi_cherchez-vous_une_nouvelle_assurance'],
        'timing' => ['switch_timing', 'delai_souscription', 'quand_souhaitez-vous_changer_d’assurance', "quand_souhaitez-vous_changer_d'assurance"],
        'company_name' => ['nom_societe'],
        'sector' => ['secteur_activite'],
        'seniority' => ['destinataire', 'anciennete'],
        'company_status' => ['statut', 'type_entreprise'],
    ];

    /** Fields that duplicate a lead column or describe the sheet row itself */
    private const NOISE = [
        '`', 'source', 'type', 'type_dassurance', 'agent', 'agents', 'etat', 'status', 'data', 'tel', 'telephone',
        'phone', 'email', 'nom_complet', 'full_name', 'name', 'date', 'doublon', 'forme_juridique',
    ];

    /**
     * @return array<string, mixed>|null
     */
    public static function for(Lead $lead): ?array
    {
        $answers = self::answers($lead->comment);
        $used = [];
        $take = function (string $field) use ($answers, &$used) {
            foreach (self::ALIASES[$field] as $key) {
                if (array_key_exists($key, $answers)) {
                    $used[] = $key;
                    $value = self::clean($answers[$key]);
                    if ($value !== null) {
                        return $value;
                    }
                }
            }

            return null;
        };

        $insuredText = $take('insured');
        $reasonText = $take('reason');
        $timingText = $take('timing');
        $insured = $insuredText ? self::insuredCode($insuredText) : null;
        $timing = $timingText ? self::timingCode($timingText) : null;

        $company = array_filter([
            'name' => $lead->company_name ?: $take('company_name'),
            'sector' => $lead->company_sector ?: $take('sector'),
            'seniority' => $take('seniority'),
            'status' => $lead->company_status ?: $take('company_status'),
        ]);

        $extra = [];
        foreach ($answers as $key => $value) {
            $key = (string) $key;
            if (in_array($key, $used, true) || in_array(mb_strtolower($key), self::NOISE, true)) {
                continue;
            }
            $clean = self::clean($value);
            if ($clean !== null) {
                $extra[] = ['key' => $key, 'label' => self::humanize($key), 'value' => $clean];
            }
        }

        // An answer that isn't one of the known choices (columns shifted in the sheet) is still shown, below
        if ($insuredText && ! $insured) {
            array_unshift($extra, ['key' => 'insured', 'label' => 'Assuré actuellement', 'value' => $insuredText]);
        }
        if ($timingText && ! $timing) {
            array_unshift($extra, ['key' => 'timing', 'label' => 'Délai', 'value' => $timingText]);
        }

        $result = array_filter([
            'insured' => $insured ? ['code' => $insured, 'label' => $insuredText] : null,
            'reason' => $reasonText ? ['label' => $reasonText] : null,
            'timing' => $timing ? ['code' => $timing, 'label' => $timingText] : null,
            'company' => $company ?: null,
            'extra' => $extra ?: null,
        ]);

        return $result ?: null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function answers(?string $comment): array
    {
        if (! $comment || ! str_starts_with(ltrim($comment), '{')) {
            return [];
        }

        $decoded = json_decode($comment, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * « prix_trop_cher » → « Prix trop cher »; {"Non": true} → « Non ».
     */
    public static function clean(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = implode(', ', array_map('strval', array_keys(array_filter($value))));
        }
        if (is_bool($value)) {
            $value = $value ? 'oui' : 'non';
        }
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim(preg_replace('/\s+/u', ' ', str_replace('_', ' ', (string) $value)));
        if ($text === '' || mb_strlen($text) > 200) {
            return null;
        }

        return mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }

    private static function insuredCode(string $text): ?string
    {
        $t = mb_strtolower($text);

        return match (true) {
            str_contains($t, 'résili') || str_contains($t, 'resili') => 'cancelled',
            in_array($t, ['oui', 'yes', 'true', 'assuré', 'assure'], true) => 'yes',
            in_array($t, ['non', 'no', 'false', 'pas assuré', 'pas assure'], true) => 'no',
            default => null,
        };
    }

    private static function timingCode(string $text): ?string
    {
        $t = mb_strtolower($text);

        return match (true) {
            str_contains($t, 'immédiat') || str_contains($t, 'immediat') || str_contains($t, 'urgent') => 'now',
            str_contains($t, '7 jours') || str_contains($t, 'semaine') => 'week',
            str_contains($t, 'mois') => 'month',
            str_contains($t, 'compar') => 'comparing',
            default => null,
        };
    }

    private static function humanize(string $key): string
    {
        return self::clean(rtrim($key, '?')) ?? $key;
    }
}
