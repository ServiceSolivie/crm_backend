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
use OpenSpout\Writer\XLSX\Options;
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

    /** How the stages are grouped on the screen: « Nouveau » is untouched, contact / follow-up / quote are in progress */
    private const GROUPS = ['untouched' => 'Non traités', 'in_progress' => 'En cours', 'validated' => 'Validés', 'lost' => 'Perdus'];

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
            'status_columns' => $this->statusColumns($statuses->pluck('code')->all()),
            'agents' => $this->byAgent($query),
            'sources' => $this->bySource($query),
            'insurances' => $this->byInsurance($query),
        ];
    }

    /**
     * The statuses found in the period, in the order of the agent table:
     * by group, then by stage, then in the catalog's order (system statuses
     * first, custom ones after).
     *
     * @param  array<int, string>  $codes
     * @return array<int, array{code: string, label: string, stage: ?string, stage_label: ?string, group: string}>
     */
    public function statusColumns(array $codes): array
    {
        $groupOrder = array_flip(array_keys(self::GROUPS));
        $stageOrder = array_flip(array_keys(self::STAGES));
        $codeOrder = array_flip($this->statuses->codes());

        return collect($codes)
            ->map(fn (string $code) => [
                'code' => $code,
                'label' => (string) $this->statuses->label($code),
                'stage' => $stage = $this->statuses->stage($code),
                'stage_label' => self::STAGES[$stage] ?? null,
                'group' => $this->group($stage),
            ])
            ->sort(fn (array $a, array $b) => [$groupOrder[$a['group']], $stageOrder[$a['stage']] ?? 99, $codeOrder[$a['code']] ?? PHP_INT_MAX]
                <=> [$groupOrder[$b['group']], $stageOrder[$b['stage']] ?? 99, $codeOrder[$b['code']] ?? PHP_INT_MAX])
            ->values()
            ->all();
    }

    /**
     * One row per agent who has imported leads of the period today (the
     * current agent: a reassigned lead counts for whoever has it now), then
     * the leads nobody has. Biggest first. Each row also counts its leads
     * per status (statuses: code => count).
     *
     * @return array<int, array<string, mixed>>
     */
    public function byAgent(Builder $query): array
    {
        $rows = $this->stagesBy($query, 'assigned_to', withStatuses: true);
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
     * quote), validated, lost. Keyed by the value ('' when empty). With
     * $withStatuses, also the count per status.
     *
     * @return Collection<string, array{received: int, untouched: int, in_progress: int, validated: int, lost: int, conversion: float, statuses?: array<string, int>}>
     */
    protected function stagesBy(Builder $query, string $column, bool $withStatuses = false): Collection
    {
        return (clone $query)->toBase()
            ->selectRaw("{$column} AS value_key, status, COUNT(*) AS total")
            ->groupBy($column, 'status')
            ->get()
            ->groupBy(fn ($row) => (string) $row->value_key)
            ->map(function (Collection $rows) use ($withStatuses) {
                $counts = ['received' => 0, 'untouched' => 0, 'in_progress' => 0, 'validated' => 0, 'lost' => 0];
                $statuses = [];

                foreach ($rows as $row) {
                    $n = (int) $row->total;
                    $counts['received'] += $n;
                    $counts[$this->group($this->statuses->stage((string) $row->status))] += $n;
                    $statuses[(string) $row->status] = $n;
                }

                return [
                    ...$counts,
                    'conversion' => $this->rate($counts['validated'], $counts['received']),
                    ...($withStatuses ? ['statuses' => $statuses] : []),
                ];
            });
    }

    protected function group(?string $stage): string
    {
        return match ($stage) {
            'new' => 'untouched',
            'won' => 'validated',
            'lost' => 'lost',
            default => 'in_progress',
        };
    }

    protected function rate(int $part, int $total): float
    {
        return $total ? round($part / $total * 100, 1) : 0.0;
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

        $options = new Options;
        $writer = new Writer($options);
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

        // "Par agent": Agent, Équipe, Reçus, then for each group its total and
        // one column per status found in the period, then Conversion.
        // Two header rows: the groups (merged over their columns), the statuses.
        $columns = collect(self::GROUPS)->map(fn (string $label, string $group) => [
            'label' => $label,
            'statuses' => array_values(array_filter($summary['status_columns'], fn (array $s) => $s['group'] === $group)),
        ]);

        $agents = $writer->addNewSheetAndMakeItCurrent();
        $agents->setName('Par agent');
        $agents->setSheetView((new SheetView)->setFreezeRow(3));
        $agents->setColumnWidth(24, 1);
        $agents->setColumnWidth(18, 2);

        $top = [new Cell\StringCell('Agent', $header), new Cell\StringCell('Équipe', $header), new Cell\StringCell('Reçus', $header)];
        $sub = [new Cell\EmptyCell(null, $header), new Cell\EmptyCell(null, $header), new Cell\EmptyCell(null, $header)];
        foreach ([0, 1, 2] as $col) {
            $options->mergeCells($col, 1, $col, 2, 1);
        }
        foreach ($columns as $group) {
            $first = count($top);
            $top[] = new Cell\StringCell($group['label'], $header);
            $sub[] = new Cell\StringCell('Total', $header);
            foreach ($group['statuses'] as $status) {
                $top[] = new Cell\EmptyCell(null, $header);
                $sub[] = new Cell\StringCell($status['group'] === 'in_progress' && $status['stage_label'] ? "{$status['label']} ({$status['stage_label']})" : $status['label'], $header);
            }
            if (count($top) - 1 > $first) {
                $options->mergeCells($first, 1, count($top) - 1, 1, 1);
            }
        }
        $options->mergeCells(count($top), 1, count($top), 2, 1);
        $top[] = new Cell\StringCell('Conversion', $header);
        $sub[] = new Cell\EmptyCell(null, $header);
        for ($col = 3; $col < count($top); $col++) {
            $agents->setColumnWidth(13, $col + 1);
        }
        $writer->addRow(new Row($top));
        $writer->addRow(new Row($sub));

        $line = function (array $agent, ?Style $style, ?Style $percentStyle) use ($writer, $columns, $date) {
            $cells = [
                new Cell\StringCell($agent['name'] ?? 'Non assigné', $style),
                $this->cell($agent['team'] ?? null, $date),
                new Cell\NumericCell($agent['received'], $style),
            ];
            foreach ($columns as $group => $definition) {
                $cells[] = new Cell\NumericCell($agent[$group], $style);
                foreach ($definition['statuses'] as $status) {
                    $cells[] = new Cell\NumericCell($agent['statuses'][$status['code']] ?? 0, $style);
                }
            }
            $cells[] = new Cell\NumericCell($agent['conversion'] / 100, $percentStyle);
            $writer->addRow(new Row($cells));
        };

        $total = ['name' => 'Total', 'received' => 0, 'untouched' => 0, 'in_progress' => 0, 'validated' => 0, 'lost' => 0, 'statuses' => []];
        foreach ($summary['agents'] as $agent) {
            $line($agent, null, $percent);
            foreach (['received', ...array_keys(self::GROUPS)] as $k) {
                $total[$k] += $agent[$k];
            }
            foreach ($agent['statuses'] as $code => $n) {
                $total['statuses'][$code] = ($total['statuses'][$code] ?? 0) + $n;
            }
        }
        $line([...$total, 'conversion' => $this->rate($total['validated'], $total['received'])], $bold, $boldPercent);

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
