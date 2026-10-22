<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\LeadStatusHistory;
use App\Models\User;
use App\Support\LeadStatusCatalog;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/**
 * The leads that came into the CRM from an import - Google Sheets (webhook
 * or sync: sheet_row_key) or an uploaded file (lead_import_id) - over a
 * week, a month or a chosen range, with their current status. Leads typed
 * in the CRM are not imports and never appear.
 *
 * Weeks and months are cut on French time (dates are stored in UTC).
 */
class ImportedLeadsExportService
{
    public const TIMEZONE = 'Europe/Paris';

    public const PERIODS = ['week', 'month', 'custom'];

    public const ORIGINS = ['all', 'google_sheets', 'file'];

    public const PREVIEW_ROWS = 10;

    private const STAGES = ['new' => 'Nouveau', 'contact' => 'Contact', 'follow_up' => 'À relancer', 'quote' => 'Devis', 'won' => 'Validé', 'lost' => 'Perdu'];

    private const ORIGIN_LABELS = ['all' => 'Toutes les importations', 'google_sheets' => 'Google Sheets', 'file' => 'Fichier importé'];

    private const INSURANCE_TYPES = [
        'AUTO' => 'Assurance Auto', 'MOTO' => 'Assurance Moto', 'RC_PRO' => 'Responsabilité Civile Pro',
        'MUTUELLE_SANTE' => 'Mutuelle Santé', 'EMPRUNTEUR' => 'Assurance Emprunteur', 'CREDIT_CONSOMMATION' => 'Crédit Consommation',
        'RACHAT_CREDIT' => 'Rachat de Crédit', 'CREDIT_IMMOBILIER' => 'Crédit Immobilier', 'DECENNALE' => 'Décennale',
        'TAXI_VTC' => 'Taxi / VTC', 'AUTRE' => 'Autre',
    ];

    private const HEADERS = [
        'Référence', 'Reçu le', 'Soumis le', 'Origine', 'Prénom', 'Nom', 'Téléphone', 'E-mail', 'Assurance', 'Source',
        'Statut', 'Étape', 'Statut changé le', 'Agent', 'Équipe', 'Doublon', 'DVC', 'Paiement', 'Validé le',
    ];

    private const WIDTHS = [16, 17, 17, 22, 16, 18, 16, 28, 22, 16, 20, 12, 17, 20, 16, 9, 22, 18, 17];

    public function __construct(protected LeadStatusCatalog $statuses) {}

    /**
     * The period asked for, in French time, with what the screen and the
     * file call it.
     *
     * @return array{period: string, from: CarbonImmutable, to: CarbonImmutable, label: string, file_name: string}
     */
    public function range(string $period, ?string $date, ?string $from = null, ?string $to = null): array
    {
        $day = fn (?string $value) => CarbonImmutable::createFromFormat('!Y-m-d', (string) $value, self::TIMEZONE);

        [$start, $end, $label, $suffix] = match ($period) {
            'week' => (function () use ($day, $date) {
                $start = $day($date)->startOfWeek(CarbonImmutable::MONDAY);
                $end = $start->endOfWeek(CarbonImmutable::SUNDAY);

                return [$start, $end, "Semaine du {$start->format('d/m/Y')} au {$end->format('d/m/Y')}", $start->isoFormat('GGGG-[S]WW')];
            })(),
            'month' => (function () use ($day, $date) {
                $start = $day($date)->startOfMonth();

                return [$start, $start->endOfMonth(), ucfirst($start->locale('fr')->isoFormat('MMMM YYYY')), $start->format('Y-m')];
            })(),
            default => (function () use ($day, $from, $to) {
                $start = $day($from)->startOfDay();
                $end = $day($to)->endOfDay();

                return [$start, $end, "Du {$start->format('d/m/Y')} au {$end->format('d/m/Y')}", $start->format('Y-m-d').'_'.$end->format('Y-m-d')];
            })(),
        };

        return ['period' => $period, 'from' => $start, 'to' => $end, 'label' => $label, 'file_name' => "leads-importes_{$suffix}.xlsx"];
    }

    /**
     * @param  array{from: CarbonImmutable, to: CarbonImmutable}  $range
     */
    public function query(array $range, string $origin): Builder
    {
        return Lead::query()
            ->where(fn (Builder $q) => match ($origin) {
                'google_sheets' => $q->whereNotNull('sheet_row_key'),
                'file' => $q->whereNotNull('lead_import_id'),
                default => $q->whereNotNull('sheet_row_key')->orWhereNotNull('lead_import_id'),
            })
            ->whereBetween('leads.created_at', [
                $range['from']->utc()->format('Y-m-d H:i:s'),
                $range['to']->utc()->format('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Counters and the breakdown by current status and by stage.
     *
     * @return array{total: int, validated: int, unassigned: int, doublons: int, statuses: array<int, array{code: string, label: string, stage: ?string, count: int}>, stages: array<int, array{stage: string, label: string, count: int}>}
     */
    public function summary(Builder $query): array
    {
        $counts = (clone $query)->toBase()
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($n) => (int) $n);

        $statuses = $counts
            ->map(fn (int $n, $code) => [
                'code' => (string) $code,
                'label' => (string) $this->statuses->label((string) $code),
                'stage' => $this->statuses->stage((string) $code),
                'count' => $n,
            ])
            ->sortByDesc('count')
            ->values();

        $stages = collect(self::STAGES)
            ->map(fn (string $label, string $stage) => [
                'stage' => $stage,
                'label' => $label,
                'count' => $statuses->where('stage', $stage)->sum('count'),
            ])
            ->values();

        return [
            'total' => $counts->sum(),
            'validated' => $stages->firstWhere('stage', 'won')['count'],
            'unassigned' => (clone $query)->whereNull('assigned_to')->count(),
            'doublons' => (clone $query)->where('is_doublon', true)->count(),
            'statuses' => $statuses->all(),
            'stages' => $stages->all(),
            'agents' => $this->byAgent($query),
            'sources' => $this->bySource($query),
            'insurances' => $this->byInsurance($query),
        ];
    }

    /**
     * One row per agent who has imported leads of the period today (the
     * current agent: a reassigned lead counts for whoever has it now), then
     * the leads nobody has. Biggest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function byAgent(Builder $query): array
    {
        $rows = $this->stagesBy($query, 'assigned_to');
        $users = User::with('team:id,name')
            ->whereIn('id', $rows->keys()->filter()->all())
            ->get(['id', 'name', 'team_id'])
            ->keyBy('id');

        return $rows
            ->map(fn (array $counts, string $id) => [
                'agent_id' => $id === '' ? null : (int) $id,
                'name' => $id === '' ? null : ($users->get((int) $id)?->name ?? '—'),
                'team' => $id === '' ? null : $users->get((int) $id)?->team?->name,
                ...$counts,
            ])
            ->sort(fn (array $a, array $b) => [$a['agent_id'] === null, $b['received']] <=> [$b['agent_id'] === null, $a['received']])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function bySource(Builder $query): array
    {
        $rows = $this->stagesBy($query, 'lead_source_id');
        $names = LeadSource::whereIn('id', $rows->keys()->filter()->all())->pluck('name', 'id');

        return $rows
            ->map(fn (array $counts, string $id) => ['source_id' => $id === '' ? null : (int) $id, 'name' => $id === '' ? null : $names->get((int) $id), ...$counts])
            ->sortByDesc('received')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function byInsurance(Builder $query): array
    {
        return $this->stagesBy($query, 'insurance_type')
            ->map(fn (array $counts, string $code) => ['code' => $code === '' ? null : $code, 'label' => self::INSURANCE_TYPES[$code] ?? ($code ?: 'Non renseignée'), ...$counts])
            ->sortByDesc('received')
            ->values()
            ->all();
    }

    /**
     * Leads per value of $column, split by the stage of their current
     * status: untouched (still « Nouveau »), in progress (contact, follow-up,
     * quote), validated, lost. Keyed by the value ('' when empty).
     *
     * @return Collection<string, array{received: int, untouched: int, in_progress: int, validated: int, lost: int, conversion: float}>
     */
    protected function stagesBy(Builder $query, string $column): Collection
    {
        return (clone $query)->toBase()
            ->selectRaw("{$column} AS value_key, status, COUNT(*) AS total")
            ->groupBy($column, 'status')
            ->get()
            ->groupBy(fn ($row) => (string) $row->value_key)
            ->map(function (Collection $rows) {
                $counts = ['received' => 0, 'untouched' => 0, 'in_progress' => 0, 'validated' => 0, 'lost' => 0];

                foreach ($rows as $row) {
                    $n = (int) $row->total;
                    $counts['received'] += $n;
                    $counts[match ($this->statuses->stage((string) $row->status)) {
                        'new' => 'untouched',
                        'won' => 'validated',
                        'lost' => 'lost',
                        default => 'in_progress',
                    }] += $n;
                }

                return [...$counts, 'conversion' => $this->rate($counts['validated'], $counts['received'])];
            });
    }

    protected function rate(int $part, int $total): float
    {
        return $total ? round($part / $total * 100, 1) : 0.0;
    }

    /**
     * The first rows of the file, newest first, for the screen.
     *
     * @return array<int, array<string, mixed>>
     */
    public function preview(Builder $query): array
    {
        return $this->withDetails($query)
            ->orderByDesc('leads.id')
            ->limit(self::PREVIEW_ROWS)
            ->get()
            ->map(fn (Lead $lead) => [
                'id' => $lead->id,
                'reference' => $lead->reference,
                'received_at' => $lead->created_at?->toIso8601String(),
                'name' => trim("{$lead->first_name} {$lead->last_name}"),
                'origin' => $this->origin($lead),
                'status' => $lead->status_code,
                'status_label' => $this->statuses->label($lead->status_code),
                'agent' => $lead->assignedAgent?->name,
                'insurance_type' => $lead->insurance_type?->value,
            ])
            ->all();
    }

    /**
     * Write the .xlsx to $path: sheets "Leads" (one row per lead, newest
     * first), "Par agent" and "Résumé". Returns the number of leads written.
     *
     * @param  array{label: string}  $range
     * @param  array<string, mixed>  $summary  as returned by summary()
     */
    public function write(Builder $query, array $range, string $origin, array $summary, User $by, string $path): int
    {
        $bold = (new Style)->setFontBold();
        $header = (new Style)->setFontBold()->setFontColor(Color::WHITE)->setBackgroundColor('4F46E5');
        $date = (new Style)->setFormat('dd/mm/yyyy hh:mm');

        $writer = new Writer;
        $writer->openToFile($path);

        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Leads');
        $sheet->setSheetView((new SheetView)->setFreezeRow(2));
        foreach (self::WIDTHS as $i => $width) {
            $sheet->setColumnWidth($width, $i + 1);
        }

        $writer->addRow(new Row(array_map(fn (string $h) => new Cell\StringCell($h, $header), self::HEADERS)));

        $written = 0;
        foreach ($this->withDetails($query)->lazyByIdDesc(500, 'leads.id', 'id') as $lead) {
            $writer->addRow(new Row(array_map(fn ($value) => $this->cell($value, $date), $this->row($lead))));
            $written++;
        }

        $sheet->setAutoFilter(new AutoFilter(0, 1, count(self::HEADERS) - 1, $written + 1));

        $percent = (new Style)->setFormat('0.0%');
        $boldPercent = (new Style)->setFontBold()->setFormat('0.0%');

        $agents = $writer->addNewSheetAndMakeItCurrent();
        $agents->setName('Par agent');
        $agents->setSheetView((new SheetView)->setFreezeRow(2));
        foreach ([24, 18, 10, 12, 10, 10, 10, 12] as $i => $width) {
            $agents->setColumnWidth($width, $i + 1);
        }
        $writer->addRow(new Row(array_map(
            fn (string $h) => new Cell\StringCell($h, $header),
            ['Agent', 'Équipe', 'Reçus', 'Non traités', 'En cours', 'Validés', 'Perdus', 'Conversion'],
        )));
        $totals = ['received' => 0, 'untouched' => 0, 'in_progress' => 0, 'validated' => 0, 'lost' => 0];
        foreach ($summary['agents'] as $agent) {
            $writer->addRow(new Row([
                $this->cell($agent['name'] ?? 'Non assigné', $date),
                $this->cell($agent['team'], $date),
                ...array_map(fn (string $k) => new Cell\NumericCell($agent[$k], null), array_keys($totals)),
                new Cell\NumericCell($agent['conversion'] / 100, $percent),
            ]));
            foreach ($totals as $k => $v) {
                $totals[$k] = $v + $agent[$k];
            }
        }
        $writer->addRow(new Row([
            new Cell\StringCell('Total', $bold),
            new Cell\EmptyCell(null, null),
            ...array_map(fn (int $v) => new Cell\NumericCell($v, $bold), array_values($totals)),
            new Cell\NumericCell($this->rate($totals['validated'], $totals['received']) / 100, $boldPercent),
        ]));

        $resume = $writer->addNewSheetAndMakeItCurrent();
        $resume->setName('Résumé');
        $resume->setColumnWidth(34, 1);
        $resume->setColumnWidth(14, 2);
        $resume->setColumnWidth(12, 3);

        $line = fn (string $label, $value = null, ?Style $style = null) => $writer->addRow(new Row([
            new Cell\StringCell($label, $style),
            $this->cell($value, $date),
        ]));

        $line('Export des leads importés', null, $bold);
        $line('Période', $range['label']);
        $line('Origine', self::ORIGIN_LABELS[$origin] ?? $origin);
        $line('Généré par', $by->name);
        $line('Généré le', now(self::TIMEZONE)->toDateTimeImmutable());
        $line('');
        $line('Leads importés', $summary['total'], $bold);
        $line('Validés', $summary['validated']);
        $line('Non assignés', $summary['unassigned']);
        $line('Doublons', $summary['doublons']);
        $line('');
        $line('Par étape', null, $bold);
        foreach ($summary['stages'] as $stage) {
            $line($stage['label'], $stage['count']);
        }
        $line('');
        $line('Par statut actuel', null, $bold);
        foreach ($summary['statuses'] as $status) {
            $line($status['label'], $status['count']);
        }

        // Count and conversion (validated ÷ received) per source and per insurance
        foreach (['Par source' => $summary['sources'], 'Par assurance' => $summary['insurances']] as $title => $groups) {
            $line('');
            $writer->addRow(new Row([new Cell\StringCell($title, $bold), new Cell\StringCell('Leads', $bold), new Cell\StringCell('Conversion', $bold)]));
            foreach ($groups as $group) {
                $writer->addRow(new Row([
                    new Cell\StringCell($group['name'] ?? $group['label'] ?? 'Sans source', null),
                    new Cell\NumericCell($group['received'], null),
                    new Cell\NumericCell($group['conversion'] / 100, $percent),
                ]));
            }
        }

        // Open on the leads
        $writer->setCurrentSheet($sheet);
        $writer->close();

        return $written;
    }

    protected function withDetails(Builder $query): Builder
    {
        return (clone $query)
            ->select('leads.*')
            ->addSelect(['status_changed_at' => LeadStatusHistory::query()
                ->select('created_at')
                ->whereColumn('lead_id', 'leads.id')
                ->latest('id')
                ->limit(1),
            ])
            ->with(['leadSource:id,name', 'assignedAgent:id,name', 'team:id,name', 'leadImport:id,file_name']);
    }

    /**
     * @return array<int, mixed>
     */
    protected function row(Lead $lead): array
    {
        return [
            $lead->reference,
            $this->local($lead->created_at),
            $this->local($lead->lead_submitted_at),
            $this->origin($lead),
            $lead->first_name,
            $lead->last_name,
            $lead->phone,
            $lead->email,
            self::INSURANCE_TYPES[$lead->insurance_type?->value] ?? $lead->insurance_type?->value,
            $lead->leadSource?->name,
            $this->statuses->label($lead->status_code),
            self::STAGES[$this->statuses->stage($lead->status_code)] ?? null,
            $this->local($lead->status_changed_at ? CarbonImmutable::parse($lead->status_changed_at, 'UTC') : null),
            $lead->assignedAgent?->name ?? 'Non assigné',
            $lead->team?->name,
            $lead->is_doublon ? 'Oui' : 'Non',
            $lead->dvc_status?->label(),
            $lead->payment_status?->label(),
            $this->local($lead->validated_at),
        ];
    }

    protected function origin(Lead $lead): string
    {
        if ($lead->sheet_row_key) {
            return 'Google Sheets';
        }

        return $lead->leadImport ? "Fichier « {$lead->leadImport->file_name} »" : 'Fichier importé';
    }

    /**
     * Excel has no time zones: write the French wall time.
     */
    protected function local(?DateTimeInterface $at): ?DateTimeInterface
    {
        return $at ? CarbonImmutable::instance($at)->setTimezone(self::TIMEZONE)->toDateTimeImmutable() : null;
    }

    /**
     * Text always goes in as text: a name or e-mail typed as "=..." in a
     * form must not become a formula in Excel.
     */
    protected function cell(mixed $value, Style $date): Cell
    {
        return match (true) {
            $value === null || $value === '' => new Cell\EmptyCell(null, null),
            $value instanceof DateTimeInterface => new Cell\DateTimeCell($value, $date),
            is_int($value) || is_float($value) => new Cell\NumericCell($value, null),
            default => new Cell\StringCell((string) $value, null),
        };
    }
}
