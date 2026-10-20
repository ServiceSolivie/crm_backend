<?php

namespace App\Services;

use App\Support\Dvc\DvcDocument;
use App\Support\Dvc\DvcSchema;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;

/**
 * Renders any DVC to PDF with the one generic view, from its schema and
 * values. Used to generate a contract and to preview a schema before the
 * super admin publishes it.
 */
class DvcPdfRenderer
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function render(DvcSchema $schema, array $data, string $reference, int $version, CarbonInterface $generatedAt): string
    {
        $config = [
            'broker' => config('contracts.broker'),
            'mediation' => config('contracts.mediation'),
            'authority' => config('contracts.authority'),
        ];

        return Pdf::loadView('contracts.dvc', [
            ...$config,
            'dvc' => new DvcDocument($schema, $data, $config),
            'reference' => $reference,
            'version' => $version,
            'generated_at' => $generatedAt,
        ])->output();
    }

    /**
     * Example values for a preview: one plausible value per field type, every
     * other guarantee ticked.
     *
     * @return array<string, mixed>
     */
    public function sampleData(DvcSchema $schema): array
    {
        $data = [];

        foreach ($schema->fields() as $key => $field) {
            $data[$key] = $field['default'] ?? match ($field['type']) {
                'date' => now()->toDateString(),
                'money' => 1234.5,
                'number' => 12,
                'select' => $field['options'][0] ?? '',
                'boolean' => true,
                'textarea' => 'Exemple de texte saisi pour « '.$field['label'].' ».',
                default => 'Exemple',
            };
        }

        $i = 0;
        foreach ($schema->garanties() as $key => $item) {
            $data["{$key}_included"] = ($item['default_included'] ?? false) || $i++ % 2 === 0;
            $data["{$key}_franchise"] = $data["{$key}_included"] ? '150 €' : '';
        }

        return $data;
    }
}
