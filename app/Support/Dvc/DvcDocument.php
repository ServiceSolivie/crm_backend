<?php

namespace App\Support\Dvc;

use Carbon\Carbon;
use Illuminate\Support\HtmlString;

/**
 * What the generic DVC view (contracts/dvc.blade.php) needs to print one DVC:
 * its schema, its values formatted by field type, and the schema's texts
 * turned into safe HTML.
 */
class DvcDocument
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array<string, mixed>>  $config  broker, mediation, authority
     */
    public function __construct(
        public readonly DvcSchema $schema,
        protected array $data,
        protected array $config,
    ) {}

    /**
     * A field's value as printed: dates d/m/Y, amounts "1 234,50 €", booleans
     * Oui/Non. Empty string when the field was left blank.
     *
     * @param  array<string, mixed>  $field
     */
    public function value(array $field): string
    {
        $value = $this->data[$field['key']] ?? null;

        if ($value === null || $value === '') {
            return '';
        }

        $formatted = match ($field['type']) {
            'date' => $this->date($value),
            'money' => is_numeric($value) ? number_format((float) $value, 2, ',', ' ').' €' : (string) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Oui' : 'Non',
            default => is_scalar($value) ? (string) $value : '',
        };

        return ($field['uppercase'] ?? false) ? mb_strtoupper($formatted) : $formatted;
    }

    public function included(string $itemKey): bool
    {
        return filter_var($this->data["{$itemKey}_included"] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    public function franchise(string $itemKey): string
    {
        return (string) ($this->data["{$itemKey}_franchise"] ?? '');
    }

    /**
     * A section with "visible_if" prints only when that value is filled in.
     *
     * @param  array<string, mixed>  $section
     */
    public function visible(array $section): bool
    {
        if (! isset($section['visible_if'])) {
            return true;
        }

        $value = $this->data[$section['visible_if']] ?? null;

        return $value !== null && $value !== '' && $value !== false;
    }

    /**
     * The fields of a "fields" section grouped into table rows: "columns"
     * fields per row, a "width": "full" field alone on its row.
     *
     * @param  array<string, mixed>  $section
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function rows(array $section): array
    {
        $columns = $section['columns'] ?? 2;
        $rows = [];
        $row = [];

        foreach ($section['fields'] as $field) {
            if (($field['width'] ?? null) === 'full') {
                if ($row) {
                    $rows[] = $row;
                    $row = [];
                }
                $rows[] = [$field];

                continue;
            }

            $row[] = $field;
            if (count($row) === $columns) {
                $rows[] = $row;
                $row = [];
            }
        }

        if ($row) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * A text of the schema as HTML: escaped, **bold** kept, {{field}} replaced
     * by its value on a fill-in line, {{broker.name}} by the config value.
     */
    public function html(string $text): HtmlString
    {
        $html = e($text);
        $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);

        $fields = $this->schema->fields();

        $html = preg_replace_callback('/\{\{\s*([^}]*?)\s*\}\}/', function (array $m) use ($fields) {
            $placeholder = html_entity_decode($m[1], ENT_QUOTES);

            if (isset($fields[$placeholder])) {
                $value = $this->value($fields[$placeholder]);

                return '<span class="fill-inline">'.($value === '' ? '&nbsp;' : e($value)).'</span>';
            }

            if (preg_match('/^(broker|mediation|authority)\.([a-z_]+)$/', $placeholder, $parts)) {
                return e((string) ($this->config[$parts[1]][$parts[2]] ?? ''));
            }

            // A guarantee's "<key>_included" / "<key>_franchise"
            if (str_ends_with($placeholder, '_included')) {
                return $this->included(substr($placeholder, 0, -9)) ? 'Oui' : 'Non';
            }
            if (str_ends_with($placeholder, '_franchise')) {
                return e($this->franchise(substr($placeholder, 0, -10)));
            }

            return '';
        }, $html);

        return new HtmlString($html);
    }

    private function date(mixed $value): string
    {
        try {
            return Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}
