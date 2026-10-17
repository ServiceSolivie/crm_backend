<?php

namespace App\Support\Dvc;

use Illuminate\Validation\Rule;

/**
 * Laravel validation rules for the values of a DVC, built from its schema:
 * the agent's input is checked against the fields the super admin declared.
 */
class DvcFieldRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(DvcSchema $schema): array
    {
        $rules = [];

        foreach ($schema->fields() as $key => $field) {
            $rules[$key] = [
                ($field['required'] ?? false) ? 'required' : 'nullable',
                ...$this->typeRules($field),
            ];
        }

        foreach (array_keys($schema->garanties()) as $key) {
            $rules["{$key}_included"] = ['nullable', 'boolean'];
            $rules["{$key}_franchise"] = ['nullable', 'string', 'max:255'];
        }

        return $rules;
    }

    /**
     * Field labels, so error messages name the field as the agent sees it.
     *
     * @return array<string, string>
     */
    public function attributes(DvcSchema $schema): array
    {
        $attributes = [];

        foreach ($schema->fields() as $key => $field) {
            $attributes[$key] = $field['label'];
        }

        foreach ($schema->garanties() as $key => $item) {
            $attributes["{$key}_included"] = $item['label'];
            $attributes["{$key}_franchise"] = "{$item['label']} (franchise)";
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<int, mixed>
     */
    private function typeRules(array $field): array
    {
        $rules = match ($field['type']) {
            'textarea' => ['string', 'max:5000'],
            'number', 'money' => ['numeric'],
            'date' => ['date'],
            'select' => [Rule::in($field['options'] ?? [])],
            'boolean' => ['boolean'],
            default => ['string', 'max:1000'],
        };

        if (isset($field['min'])) {
            $rules[] = "min:{$field['min']}";
        }
        if (isset($field['max'])) {
            $rules[] = "max:{$field['max']}";
        }

        return $rules;
    }
}
