<?php

namespace App\Support\Dvc;

use App\Models\Lead;
use App\Models\User;

/**
 * Values a new DVC form opens with: each field's "default", then its
 * "prefill" source read from the lead and the agent. The schema can only name
 * a source from SOURCES — no code or free path in the JSON.
 *
 * The agent is the lead's assigned agent, or the user generating the DVC.
 * Every value stays editable in the form.
 */
class DvcPrefill
{
    public const SOURCES = [
        'lead.first_name',
        'lead.last_name',
        'lead.full_name',
        'lead.birth_date',
        'lead.phone',
        'lead.email',
        'lead.address',
        'lead.city',
        'lead.full_address',
        'lead.company_name',
        'lead.company_legal_form',
        'lead.company_sector',
        'lead.company_employee_count',
        'lead.company_annual_revenue',
        'agent.name',
        'agent.email',
        'agent.phone',
        'today',
    ];

    /**
     * @return array<string, mixed>
     */
    public function values(DvcSchema $schema, ?Lead $lead, User $user): array
    {
        $agent = $lead?->assignedAgent ?? $user;
        $values = [];

        foreach ($schema->fields() as $key => $field) {
            if (array_key_exists('default', $field)) {
                $values[$key] = $field['default'];
            }

            if (isset($field['prefill'])) {
                $value = $this->source($field['prefill'], $lead, $agent);

                if ($value !== null && $value !== '') {
                    // A text field holds text even when the lead stores a number
                    $values[$key] = in_array($field['type'], ['number', 'money'], true) ? $value : (string) $value;
                }
            }
        }

        foreach ($schema->garanties() as $key => $item) {
            if ($item['default_included'] ?? false) {
                $values["{$key}_included"] = true;
            }
        }

        return $values;
    }

    public function source(string $source, ?Lead $lead, User $agent): mixed
    {
        if (str_starts_with($source, 'lead.') && $lead === null) {
            return null;
        }

        return match ($source) {
            'lead.full_name' => trim("{$lead->first_name} {$lead->last_name}"),
            'lead.birth_date' => $lead->birth_date?->toDateString(),
            'lead.full_address' => trim(implode(' ', array_filter([$lead->address, $lead->city]))),
            'agent.name' => $agent->name,
            'agent.email' => $agent->email,
            'agent.phone' => $agent->phone ?? config('contracts.broker.phone'),
            'today' => now()->toDateString(),
            default => str_starts_with($source, 'lead.') && in_array($source, self::SOURCES, true)
                ? $this->scalar($lead->getAttribute(substr($source, 5)))
                : null,
        };
    }

    private function scalar(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }
}
