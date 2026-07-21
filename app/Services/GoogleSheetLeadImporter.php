<?php

namespace App\Services;

use App\Enums\InsuranceTypeEnum;
use App\Models\GoogleSheetSyncLog;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

class GoogleSheetLeadImporter
{
    protected const SHEET_COLUMNS = [
        'Lead' => [
            'source' => 0,
            'name' => 1,
            'email' => 2,
            'phone' => 3,
            'postal' => 4,
            'insurance_type' => 5,
            'agent' => 6,
            'doublon' => 7,
            'date' => 8,
        ],
        'Decennale' => [
            'source' => 0,
            'name' => 8,
            'phone' => 9,
            'email' => 10,
            'address' => 11,
            'postal' => 12,
            'agent' => 15,
            'insurance_type' => null,
            'fixed_insurance_type' => 'DECENNALE',
        ],
        'Qualite' => [
            'source' => 0,
            'name' => 5,
            'email' => 6,
            'phone' => 7,
            'insurance_type' => 8,
            'agent' => 9,
            'date' => 11,
        ],
    ];

    protected const INSURANCE_TYPE_MAP = [
        'auto' => 'AUTO',
        'moto' => 'MOTO',
        'decennale' => 'DECENNALE',
        'décennale' => 'DECENNALE',
        'taxi / vtc' => 'TAXI_VTC',
        'taxi/vtc' => 'TAXI_VTC',
        'autre' => 'AUTRE',
        'emprunteur' => 'EMPRUNTEUR',
        'rc pro' => 'RC_PRO',
        'rc_pro' => 'RC_PRO',
        'mutuelle sante' => 'MUTUELLE_SANTE',
        'mutuelle santé' => 'MUTUELLE_SANTE',
        'credit consommation' => 'CREDIT_CONSOMMATION',
        'rachat credit' => 'RACHAT_CREDIT',
        'credit immobilier' => 'CREDIT_IMMOBILIER',
    ];

    protected array $agentCache = [];

    protected array $sourceCache = [];

    public function __construct(
        protected GoogleSheetsService $sheetsService,
        protected LeadService $leadService,
    ) {}

    public function import(string $sheetName, ?string $dateFilter = null, ?int $fromRow = null): GoogleSheetSyncLog
    {
        $columns = self::SHEET_COLUMNS[$sheetName] ?? null;

        if (! $columns) {
            throw new \InvalidArgumentException("Unknown sheet: {$sheetName}. Supported: " . implode(', ', array_keys(self::SHEET_COLUMNS)));
        }

        $log = GoogleSheetSyncLog::create([
            'sheet_name' => $sheetName,
            'started_at' => now(),
        ]);

        if ($dateFilter && isset($columns['date'])) {
            [$startRow, $endRow] = $this->findDateRange($sheetName, $columns['date'], $dateFilter);
            if (! $startRow) {
                $log->update([
                    'total_rows' => 0, 'imported' => 0, 'skipped' => 0, 'failed' => 0,
                    'last_row_synced' => 0, 'completed_at' => now(),
                ]);

                return $log->refresh();
            }
            $rows = $this->sheetsService->getRows($sheetName, $startRow, $endRow);
        } else {
            $startRow = $fromRow ?? $this->getLastSyncedRow($sheetName) + 1;
            if ($startRow < 2) {
                $startRow = 2;
            }
            $rows = $this->sheetsService->getRows($sheetName, $startRow);
        }

        $imported = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];
        $lastRow = $startRow - 1;

        $systemUser = User::where('email', 'akkaoui@crm.test')->first()
            ?? User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->first();

        foreach ($rows as $index => $row) {
            $rowNumber = $startRow + $index;
            $lastRow = $rowNumber;

            try {
                $parsed = $this->parseRow($row, $columns);

                if (strcasecmp(trim($parsed['doublon']), 'DOUBLON') === 0) {
                    $skipped++;
                    continue;
                }

                if (! $parsed['phone'] && ! $parsed['email']) {
                    $skipped++;
                    continue;
                }

                $existing = $this->findExisting($parsed['phone'], $parsed['email']);

                if ($existing) {
                    $agentId = $this->resolveAgent($parsed['agent']);
                    if ($agentId && $agentId !== $existing->assigned_to) {
                        $this->leadService->assign($existing, $agentId);
                        $imported++;
                    } else {
                        $skipped++;
                    }
                    continue;
                }

                $leadData = $this->buildLeadData($parsed);
                $this->leadService->createLead($leadData, $systemUser);
                $imported++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = ['row' => $rowNumber, 'error' => $e->getMessage()];
            }
        }

        $log->update([
            'total_rows' => count($rows),
            'imported' => $imported,
            'skipped' => $skipped,
            'failed' => $failed,
            'last_row_synced' => $lastRow,
            'completed_at' => now(),
            'error_details' => $errors ?: null,
        ]);

        return $log->refresh();
    }

    protected function findDateRange(string $sheetName, int $dateCol, string $targetDate): array
    {
        $colLetter = chr(ord('A') + $dateCol);
        $dateValues = $this->sheetsService->getDateColumn($sheetName, $colLetter);

        $target = Carbon::parse($targetDate)->format('n/j/Y');
        $firstRow = null;
        $lastRow = null;

        foreach ($dateValues as $index => $val) {
            $rowNumber = 2 + $index;
            $dateStr = trim($val);

            if (! $dateStr) {
                continue;
            }

            if (str_starts_with($dateStr, $target)) {
                if ($firstRow === null) {
                    $firstRow = $rowNumber;
                }
                $lastRow = $rowNumber;
            } elseif ($lastRow !== null) {
                $parsed = $this->parseDate($dateStr);
                if ($parsed && $parsed->format('Y-m-d') > $targetDate) {
                    break;
                }
            }
        }

        if (! $firstRow || ! $lastRow) {
            return [null, null];
        }

        return [$firstRow, $lastRow];
    }

    protected function parseRow(array $row, array $columns): array
    {
        $get = fn (int|null $col) => $col !== null ? trim($row[$col] ?? '') : '';

        $phone = $get($columns['phone'] ?? null);
        $phone = preg_replace('/^p:/', '', $phone);
        $phone = trim($phone);

        $postal = $get($columns['postal'] ?? null);
        $postal = preg_replace('/^z:/', '', $postal);

        $insuranceType = $columns['fixed_insurance_type']
            ?? $this->mapInsuranceType($get($columns['insurance_type'] ?? null));

        return [
            'name' => $get($columns['name']),
            'email' => $get($columns['email'] ?? null),
            'phone' => $phone,
            'postal' => $postal,
            'insurance_type' => $insuranceType,
            'agent' => $get($columns['agent'] ?? null),
            'source' => $get($columns['source'] ?? null),
            'date' => $get($columns['date'] ?? null),
            'address' => $get($columns['address'] ?? null),
            'doublon' => $get($columns['doublon'] ?? null),
        ];
    }

    protected function mapInsuranceType(string $raw): ?string
    {
        if (! $raw) {
            return null;
        }

        $key = strtolower(trim($raw));

        if (isset(self::INSURANCE_TYPE_MAP[$key])) {
            return self::INSURANCE_TYPE_MAP[$key];
        }

        if (InsuranceTypeEnum::tryFrom(strtoupper($raw))) {
            return strtoupper($raw);
        }

        return 'AUTRE';
    }

    protected function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), 2);

        return [
            'first_name' => $parts[0] ?? '',
            'last_name' => $parts[1] ?? $parts[0] ?? '',
        ];
    }

    protected function findExisting(?string $phone, ?string $email): ?Lead
    {
        if (! $phone && ! $email) {
            return null;
        }

        $query = Lead::query();

        if ($phone && $email) {
            $query->where(function ($q) use ($phone, $email) {
                $q->where('phone', $phone)->orWhere('email', $email);
            });
        } elseif ($phone) {
            $query->where('phone', $phone);
        } else {
            $query->where('email', $email);
        }

        return $query->first();
    }

    protected function resolveAgent(string $agentName): ?int
    {
        $agentName = trim($agentName);
        if (! $agentName) {
            return null;
        }

        $key = strtoupper($agentName);
        $key = rtrim($key, '_');

        if (isset($this->agentCache[$key])) {
            return $this->agentCache[$key];
        }

        $user = User::whereRaw('UPPER(name) LIKE ?', ["%{$key}%"])->first();

        $this->agentCache[$key] = $user?->id;

        return $user?->id;
    }

    protected function resolveSource(string $sourceName): ?int
    {
        $sourceName = trim($sourceName);
        if (! $sourceName) {
            return null;
        }

        if (isset($this->sourceCache[$sourceName])) {
            return $this->sourceCache[$sourceName];
        }

        $source = LeadSource::firstOrCreate(
            ['code' => Str::slug($sourceName)],
            ['name' => ucfirst($sourceName), 'is_active' => true]
        );

        $this->sourceCache[$sourceName] = $source->id;

        return $source->id;
    }

    protected function buildLeadData(array $parsed): array
    {
        $name = $this->splitName($parsed['name']);

        return [
            'first_name' => $name['first_name'],
            'last_name' => $name['last_name'],
            'phone' => $parsed['phone'] ?: null,
            'email' => $parsed['email'] ?: null,
            'city' => $parsed['postal'] ?: null,
            'insurance_type' => $parsed['insurance_type'],
            'assigned_to' => $this->resolveAgent($parsed['agent']),
            'lead_source_id' => $this->resolveSource($parsed['source']),
            'comment' => $parsed['address'] ? "Address: {$parsed['address']}" : null,
        ];
    }

    protected function parseDate(string $raw): ?Carbon
    {
        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function getLastSyncedRow(string $sheetName): int
    {
        return (int) GoogleSheetSyncLog::where('sheet_name', $sheetName)
            ->whereNotNull('completed_at')
            ->max('last_row_synced');
    }
}
