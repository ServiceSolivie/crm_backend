<?php

namespace App\Support\Dvc;

/**
 * A DVC type's JSON schema: document title, then sections rendered in order
 * both as the agent's form and as the PDF.
 *
 *   { "title", "subtitle"?, "hero_image"?, "sections": [ ... ] }
 *
 * Section types (all accept "title", "number", "level" 1|2, "page_break_before"):
 *   fields    — "key", "columns" 1-3, "fields": [{ "key", "label", "type", ... }]
 *   garanties — "key", "items": [{ "key", "label", "default_included"? }]
 *               (stores "<key>_included" and "<key>_franchise")
 *   text      — "paragraphs": [...], "style" plain|info, "visible_if"?: data key
 *   list      — "intro"?, "items": [...]
 *   broker    — the broker's legal identity and regulatory notice (from config)
 *
 * Texts accept **bold** and {{placeholders}}: a data key, or broker.*,
 * mediation.*, authority.* (config/contracts.php).
 *
 * The schema is checked by DvcSchemaValidator before it is saved, so this
 * class trusts its shape.
 */
class DvcSchema
{
    public const SECTION_TYPES = ['fields', 'garanties', 'text', 'list', 'broker'];

    public const FIELD_TYPES = ['text', 'textarea', 'number', 'money', 'date', 'select', 'boolean'];

    /**
     * @param  array<string, mixed>  $schema
     */
    public function __construct(protected array $schema) {}

    public function title(): string
    {
        return (string) ($this->schema['title'] ?? '');
    }

    public function subtitle(): ?string
    {
        return $this->schema['subtitle'] ?? null;
    }

    public function heroImage(): ?string
    {
        return $this->schema['hero_image'] ?? null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function sections(): array
    {
        return $this->schema['sections'] ?? [];
    }

    /**
     * Every input field of the "fields" sections, keyed by field key.
     *
     * @return array<string, array<string, mixed>>
     */
    public function fields(): array
    {
        $fields = [];

        foreach ($this->sections() as $section) {
            if (($section['type'] ?? null) === 'fields') {
                foreach ($section['fields'] ?? [] as $field) {
                    $fields[$field['key']] = $field;
                }
            }
        }

        return $fields;
    }

    /**
     * Every guarantee of the "garanties" sections, keyed by item key.
     *
     * @return array<string, array<string, mixed>>
     */
    public function garanties(): array
    {
        $items = [];

        foreach ($this->sections() as $section) {
            if (($section['type'] ?? null) === 'garanties') {
                foreach ($section['items'] ?? [] as $item) {
                    $items[$item['key']] = $item;
                }
            }
        }

        return $items;
    }

    /**
     * Every key a DVC of this schema stores in contracts.data.
     *
     * @return array<int, string>
     */
    public function dataKeys(): array
    {
        $keys = array_keys($this->fields());

        foreach (array_keys($this->garanties()) as $key) {
            $keys[] = "{$key}_included";
            $keys[] = "{$key}_franchise";
        }

        return $keys;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->schema;
    }
}
