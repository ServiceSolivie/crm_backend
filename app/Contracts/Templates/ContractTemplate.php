<?php

namespace App\Contracts\Templates;

use App\Models\Lead;
use App\Models\User;

/**
 * A contract template couples a Blade view (the printable document) with a
 * field schema (which drives the generation form on the frontend) and a
 * prefill map (which CRM data fills which field).
 *
 * Adding a new contract type = one subclass + one Blade view, registered
 * in ContractTemplateRegistry::TEMPLATES.
 */
abstract class ContractTemplate
{
    abstract public function key(): string;

    abstract public function label(): string;

    /**
     * Blade view rendered by dompdf.
     */
    abstract public function view(): string;

    /**
     * Filename prefix for generated documents (e.g. "DVC").
     */
    abstract public function filenamePrefix(): string;

    /**
     * Sections + fields describing the generation form.
     *
     * Section shape: ['key', 'title', 'type' => 'fields'|'garanties', 'fields'|'items' => [...]]
     * Field shape:   ['key', 'label', 'type' => 'text'|'date'|'number'|'select'|'textarea', 'options' => [...]?]
     *
     * @return array<int, array<string, mixed>>
     */
    abstract public function schema(): array;

    /**
     * Values resolved from CRM data (lead, agent, defaults), keyed by
     * field key. Every field stays editable on the frontend.
     *
     * @return array<string, mixed>
     */
    abstract public function prefill(?Lead $lead, User $agent): array;

    /**
     * Every valid data key for this template (used to whitelist input).
     *
     * @return array<int, string>
     */
    public function fieldKeys(): array
    {
        $keys = [];

        foreach ($this->schema() as $section) {
            if (($section['type'] ?? 'fields') === 'garanties') {
                foreach ($section['items'] as $item) {
                    $keys[] = "{$item['key']}_included";
                    $keys[] = "{$item['key']}_franchise";
                }
            } else {
                foreach ($section['fields'] as $field) {
                    $keys[] = $field['key'];
                }
            }
        }

        return $keys;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key(),
            'label' => $this->label(),
            'filename_prefix' => $this->filenamePrefix(),
            'schema' => $this->schema(),
        ];
    }
}
