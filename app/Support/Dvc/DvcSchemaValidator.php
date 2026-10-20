<?php

namespace App\Support\Dvc;

/**
 * Checks a DVC schema written by the super admin before it is saved: shape,
 * types, unique keys, known prefill sources and placeholders. Unknown
 * properties are refused too, so a typo ("lable") is reported instead of
 * silently ignored.
 *
 * Returns the errors keyed by their path in the JSON ("sections.3.fields.0.key"),
 * an empty array when the schema is valid.
 */
class DvcSchemaValidator
{
    private const KEY_PATTERN = '/^[a-z][a-z0-9_]{0,59}$/';

    private const COMMON_SECTION_PROPERTIES = ['type', 'title', 'number', 'level', 'page_break_before'];

    private const SECTION_PROPERTIES = [
        'fields' => ['key', 'columns', 'fields'],
        'garanties' => ['key', 'items'],
        'text' => ['paragraphs', 'style', 'visible_if'],
        'list' => ['intro', 'items'],
        'broker' => [],
    ];

    private const FIELD_PROPERTIES = ['key', 'label', 'type', 'required', 'options', 'prefill', 'default', 'width', 'min', 'max', 'uppercase', 'help'];

    /** @var array<string, string> */
    private array $errors = [];

    /** @var array<string, string> data key => path where it was declared */
    private array $dataKeys = [];

    /** @var array<int, array{path: string, text: string}> texts whose placeholders are checked at the end */
    private array $texts = [];

    /** @var array<int, array{path: string, key: string}> */
    private array $visibleIf = [];

    /**
     * @param  array<int, string>|null  $heroImages  allowed hero image file names (default: public/images/contracts/*-hero.*)
     * @param  array<string, array<string, mixed>>|null  $config  broker / mediation / authority (default: config/contracts.php)
     */
    public function __construct(
        protected ?array $heroImages = null,
        protected ?array $config = null,
    ) {}

    /**
     * @return array<string, string>
     */
    public function validate(mixed $schema): array
    {
        $this->errors = [];
        $this->dataKeys = [];
        $this->texts = [];
        $this->visibleIf = [];

        if (! is_array($schema) || array_is_list($schema)) {
            return ['schema' => 'Le schéma doit être un objet JSON.'];
        }

        $this->unknownProperties($schema, ['title', 'subtitle', 'hero_image', 'sections'], 'schema');
        $this->requiredString($schema, 'title', 'schema', 120);
        $this->optionalString($schema, 'subtitle', 'schema', 500);

        if (isset($schema['hero_image']) && ! in_array($schema['hero_image'], $this->heroImages(), true)) {
            $this->errors['schema.hero_image'] = 'Image inconnue. Images disponibles : '.(implode(', ', $this->heroImages()) ?: 'aucune').'.';
        }

        $sections = $schema['sections'] ?? null;
        if (! is_array($sections) || ! array_is_list($sections) || $sections === []) {
            $this->errors['sections'] = 'Le schéma doit contenir au moins une section (tableau "sections").';

            return $this->errors;
        }

        $sectionKeys = [];
        foreach ($sections as $i => $section) {
            $path = "sections.{$i}";

            if (! is_array($section) || array_is_list($section)) {
                $this->errors[$path] = 'Chaque section doit être un objet JSON.';

                continue;
            }

            $type = $section['type'] ?? null;
            if (! in_array($type, DvcSchema::SECTION_TYPES, true)) {
                $this->errors["{$path}.type"] = 'Type de section inconnu. Types possibles : '.implode(', ', DvcSchema::SECTION_TYPES).'.';

                continue;
            }

            $this->unknownProperties($section, [...self::COMMON_SECTION_PROPERTIES, ...self::SECTION_PROPERTIES[$type]], $path);
            $this->validateCommon($section, $path, in_array($type, ['fields', 'garanties', 'broker'], true));

            if (in_array($type, ['fields', 'garanties'], true)) {
                if ($this->key($section, $path)) {
                    if (isset($sectionKeys[$section['key']])) {
                        $this->errors["{$path}.key"] = "La clé de section « {$section['key']} » est déjà utilisée.";
                    }
                    $sectionKeys[$section['key']] = true;
                }
            }

            match ($type) {
                'fields' => $this->validateFieldsSection($section, $path),
                'garanties' => $this->validateGarantiesSection($section, $path),
                'text' => $this->validateTextSection($section, $path),
                'list' => $this->validateListSection($section, $path),
                'broker' => null,
            };
        }

        if ($this->dataKeys === []) {
            $this->errors['sections'] = 'Le schéma doit contenir au moins un champ à remplir.';
        }

        // Placeholders and visible_if can point to a field declared further down
        foreach ($this->texts as $text) {
            $this->validatePlaceholders($text['text'], $text['path']);
        }
        foreach ($this->visibleIf as $condition) {
            if (! isset($this->dataKeys[$condition['key']])) {
                $this->errors[$condition['path']] = "« {$condition['key']} » n'est pas un champ du schéma.";
            }
        }

        return $this->errors;
    }

    /**
     * @param  array<string, mixed>  $section
     */
    private function validateCommon(array $section, string $path, bool $titleRequired): void
    {
        $titleRequired
            ? $this->requiredString($section, 'title', $path, 150)
            : $this->optionalString($section, 'title', $path, 150);
        $this->optionalString($section, 'number', $path, 10);

        if (isset($section['level']) && ! in_array($section['level'], [1, 2], true)) {
            $this->errors["{$path}.level"] = 'Le niveau de titre vaut 1 ou 2.';
        }
        if (isset($section['page_break_before']) && ! is_bool($section['page_break_before'])) {
            $this->errors["{$path}.page_break_before"] = 'Valeur attendue : true ou false.';
        }
    }

    /**
     * @param  array<string, mixed>  $section
     */
    private function validateFieldsSection(array $section, string $path): void
    {
        if (isset($section['columns']) && ! in_array($section['columns'], [1, 2, 3], true)) {
            $this->errors["{$path}.columns"] = 'Le nombre de colonnes vaut 1, 2 ou 3.';
        }

        $fields = $section['fields'] ?? null;
        if (! is_array($fields) || ! array_is_list($fields) || $fields === []) {
            $this->errors["{$path}.fields"] = 'Une section « fields » contient au moins un champ (tableau "fields").';

            return;
        }

        foreach ($fields as $j => $field) {
            $fieldPath = "{$path}.fields.{$j}";

            if (! is_array($field) || array_is_list($field)) {
                $this->errors[$fieldPath] = 'Chaque champ doit être un objet JSON.';

                continue;
            }

            $this->unknownProperties($field, self::FIELD_PROPERTIES, $fieldPath);
            $this->requiredString($field, 'label', $fieldPath, 150);
            $this->optionalString($field, 'help', $fieldPath, 255);

            if ($this->key($field, $fieldPath)) {
                $this->declareDataKey($field['key'], "{$fieldPath}.key");
            }

            $type = $field['type'] ?? null;
            if (! in_array($type, DvcSchema::FIELD_TYPES, true)) {
                $this->errors["{$fieldPath}.type"] = 'Type de champ inconnu. Types possibles : '.implode(', ', DvcSchema::FIELD_TYPES).'.';

                continue;
            }

            foreach (['required', 'uppercase'] as $flag) {
                if (isset($field[$flag]) && ! is_bool($field[$flag])) {
                    $this->errors["{$fieldPath}.{$flag}"] = 'Valeur attendue : true ou false.';
                }
            }

            if ($type === 'select') {
                $options = $field['options'] ?? null;
                $valid = is_array($options) && array_is_list($options) && $options !== []
                    && count(array_filter($options, fn ($o) => is_string($o) && trim($o) !== '')) === count($options)
                    && count(array_unique($options)) === count($options);
                if (! $valid) {
                    $this->errors["{$fieldPath}.options"] = 'Une liste déroulante a besoin d\'options : un tableau de textes non vides et différents.';
                }
            } elseif (isset($field['options'])) {
                $this->errors["{$fieldPath}.options"] = 'Seul un champ « select » a des options.';
            }

            foreach (['min', 'max'] as $bound) {
                if (! isset($field[$bound])) {
                    continue;
                }
                if (! in_array($type, ['number', 'money'], true)) {
                    $this->errors["{$fieldPath}.{$bound}"] = "« {$bound} » ne s'applique qu'aux champs number et money.";
                } elseif (! is_int($field[$bound]) && ! is_float($field[$bound])) {
                    $this->errors["{$fieldPath}.{$bound}"] = 'Valeur numérique attendue.';
                }
            }
            if (isset($field['min'], $field['max']) && is_numeric($field['min']) && is_numeric($field['max']) && $field['min'] > $field['max']) {
                $this->errors["{$fieldPath}.max"] = '« max » doit être supérieur ou égal à « min ».';
            }

            if (isset($field['uppercase']) && ! in_array($type, ['text', 'textarea'], true)) {
                $this->errors["{$fieldPath}.uppercase"] = '« uppercase » ne s\'applique qu\'aux champs texte.';
            }

            if (isset($field['width']) && $field['width'] !== 'full') {
                $this->errors["{$fieldPath}.width"] = 'Seule la valeur « full » est possible (champ sur toute la largeur).';
            }

            if (isset($field['prefill']) && ! in_array($field['prefill'], DvcPrefill::SOURCES, true)) {
                $this->errors["{$fieldPath}.prefill"] = 'Source inconnue. Sources possibles : '.implode(', ', DvcPrefill::SOURCES).'.';
            }

            if (array_key_exists('default', $field) && ! $this->validDefault($field['default'], $type, $field)) {
                $this->errors["{$fieldPath}.default"] = 'Valeur par défaut incompatible avec le type du champ.';
            }
        }
    }

    /**
     * @param  array<string, mixed>  $section
     */
    private function validateGarantiesSection(array $section, string $path): void
    {
        $items = $section['items'] ?? null;
        if (! is_array($items) || ! array_is_list($items) || $items === []) {
            $this->errors["{$path}.items"] = 'Une section « garanties » contient au moins une garantie (tableau "items").';

            return;
        }

        foreach ($items as $j => $item) {
            $itemPath = "{$path}.items.{$j}";

            if (! is_array($item) || array_is_list($item)) {
                $this->errors[$itemPath] = 'Chaque garantie doit être un objet JSON.';

                continue;
            }

            $this->unknownProperties($item, ['key', 'label', 'default_included'], $itemPath);
            $this->requiredString($item, 'label', $itemPath, 150);

            if (isset($item['default_included']) && ! is_bool($item['default_included'])) {
                $this->errors["{$itemPath}.default_included"] = 'Valeur attendue : true ou false.';
            }

            if ($this->key($item, $itemPath)) {
                $this->declareDataKey("{$item['key']}_included", "{$itemPath}.key");
                $this->declareDataKey("{$item['key']}_franchise", "{$itemPath}.key");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $section
     */
    private function validateTextSection(array $section, string $path): void
    {
        $this->stringList($section, 'paragraphs', $path, 5000);

        if (isset($section['style']) && ! in_array($section['style'], ['plain', 'info'], true)) {
            $this->errors["{$path}.style"] = 'Le style vaut « plain » ou « info » (encadré).';
        }

        if (isset($section['visible_if'])) {
            if (! is_string($section['visible_if'])) {
                $this->errors["{$path}.visible_if"] = 'Clé de champ attendue.';
            } else {
                $this->visibleIf[] = ['path' => "{$path}.visible_if", 'key' => $section['visible_if']];
            }
        }
    }

    /**
     * @param  array<string, mixed>  $section
     */
    private function validateListSection(array $section, string $path): void
    {
        $this->optionalString($section, 'intro', $path, 2000);
        if (is_string($section['intro'] ?? null)) {
            $this->texts[] = ['path' => "{$path}.intro", 'text' => $section['intro']];
        }

        $this->stringList($section, 'items', $path, 500);
    }

    private function validatePlaceholders(string $text, string $path): void
    {
        preg_match_all('/\{\{\s*([^}]*?)\s*\}\}/', $text, $matches);

        foreach ($matches[1] as $placeholder) {
            if (isset($this->dataKeys[$placeholder]) || $this->isConfigPlaceholder($placeholder)) {
                continue;
            }

            $this->errors[$path] = "Variable inconnue : {{{$placeholder}}}. Utilisez la clé d'un champ du schéma, ou broker.*, mediation.*, authority.*.";

            return;
        }
    }

    private function isConfigPlaceholder(string $placeholder): bool
    {
        if (! preg_match('/^(broker|mediation|authority)\.([a-z_]+)$/', $placeholder, $m)) {
            return false;
        }

        $config = $this->config ?? config('contracts');

        return isset($config[$m[1]][$m[2]]) && is_scalar($config[$m[1]][$m[2]]);
    }

    /**
     * @param  array<string, mixed>  $array
     */
    private function stringList(array $array, string $property, string $path, int $max): void
    {
        $list = $array[$property] ?? null;
        if (! is_array($list) || ! array_is_list($list) || $list === []) {
            $this->errors["{$path}.{$property}"] = "Tableau de textes attendu (au moins un élément) dans \"{$property}\".";

            return;
        }

        foreach ($list as $k => $text) {
            if (! is_string($text) || trim($text) === '') {
                $this->errors["{$path}.{$property}.{$k}"] = 'Texte non vide attendu.';
            } elseif (mb_strlen($text) > $max) {
                $this->errors["{$path}.{$property}.{$k}"] = "Texte trop long ({$max} caractères maximum).";
            } else {
                $this->texts[] = ['path' => "{$path}.{$property}.{$k}", 'text' => $text];
            }
        }
    }

    /**
     * @param  array<string, mixed>  $array
     */
    private function key(array $array, string $path): bool
    {
        $key = $array['key'] ?? null;

        if (! is_string($key) || ! preg_match(self::KEY_PATTERN, $key)) {
            $this->errors["{$path}.key"] = 'Clé attendue : minuscules, chiffres et « _ », commençant par une lettre (ex. vehicle_brand).';

            return false;
        }

        return true;
    }

    private function declareDataKey(string $key, string $path): void
    {
        if (isset($this->dataKeys[$key])) {
            $this->errors[$path] = "La clé « {$key} » est déjà utilisée ({$this->dataKeys[$key]}).";

            return;
        }

        $this->dataKeys[$key] = $path;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function validDefault(mixed $default, string $type, array $field): bool
    {
        return match ($type) {
            'boolean' => is_bool($default),
            'number', 'money' => is_int($default) || is_float($default),
            'date' => is_string($default) && strtotime($default) !== false,
            'select' => in_array($default, $field['options'] ?? [], true),
            default => is_string($default),
        };
    }

    /**
     * @param  array<string, mixed>  $array
     */
    private function requiredString(array $array, string $property, string $path, int $max): void
    {
        $value = $array[$property] ?? null;

        if (! is_string($value) || trim($value) === '') {
            $this->errors["{$path}.{$property}"] = "« {$property} » est obligatoire.";
        } elseif (mb_strlen($value) > $max) {
            $this->errors["{$path}.{$property}"] = "« {$property} » : {$max} caractères maximum.";
        }
    }

    /**
     * @param  array<string, mixed>  $array
     */
    private function optionalString(array $array, string $property, string $path, int $max): void
    {
        if (! array_key_exists($property, $array) || $array[$property] === null) {
            return;
        }

        if (! is_string($array[$property])) {
            $this->errors["{$path}.{$property}"] = "« {$property} » doit être un texte.";
        } elseif (mb_strlen($array[$property]) > $max) {
            $this->errors["{$path}.{$property}"] = "« {$property} » : {$max} caractères maximum.";
        }
    }

    /**
     * @param  array<string, mixed>  $array
     * @param  array<int, string>  $allowed
     */
    private function unknownProperties(array $array, array $allowed, string $path): void
    {
        foreach (array_diff(array_keys($array), $allowed) as $property) {
            $this->errors["{$path}.{$property}"] = "Propriété inconnue « {$property} ». Propriétés possibles : ".implode(', ', $allowed).'.';
        }
    }

    /**
     * @return array<int, string>
     */
    private function heroImages(): array
    {
        return $this->heroImages ??= array_values(array_filter(
            array_map('basename', glob(public_path('images/contracts/*-hero.*')) ?: []),
            fn (string $file) => preg_match('/\.(jpe?g|png)$/i', $file) === 1,
        ));
    }
}
