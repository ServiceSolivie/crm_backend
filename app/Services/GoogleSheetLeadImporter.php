<?php

namespace App\Services;

use App\Enums\InsuranceTypeEnum;
use App\Models\GoogleSheetSyncLog;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\User;
use App\Notifications\LeadReceivedNotification;
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
            'status' => 1,
            'client_type_source' => 2,
            'legal_form' => 3,
            'sector' => 4,
            'employee_count' => 5,
            'company_name' => 6,
            'annual_revenue' => 7,
            'name' => 8,
            'phone' => 9,
            'email' => 10,
            'address' => 11,
            'postal' => 12,
            'agent' => 15,
            'insurance_type' => null,
            'fixed_insurance_type' => 'DECENNALE',
        ],
        'Detailles' => [
            'source' => 0,
            'currently_insured' => 1,
            'switch_reason' => 2,
            'switch_timing' => 3,
            'name' => 4,
            'phone_alt' => 5,
            'phone' => 6,
            'insurance_type' => 8,
            'agent' => 9,
            'doublon' => 10,
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

                if ($this->processParsedRow($parsed, $systemUser) === 'imported') {
                    $imported++;
                } else {
                    $skipped++;
                }
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

    /**
     * One-time bulk seed: mirrors a sheet's CURRENT full state into the CRM
     * as a starting baseline. Unlike import()/processParsedRow(), this
     * ignores the `doublon` flag entirely, skips any row with no agent in
     * the sheet, and skips anything already in the CRM (pure additive
     * seeding — never updates or reassigns an existing lead).
     *
     * @return array{total: int, imported: int, skipped: int, failed: int, errors: array}
     */
    public function seedFromSheet(string $sheetName): array
    {
        $columns = self::SHEET_COLUMNS[$sheetName] ?? null;

        if (! $columns) {
            throw new \InvalidArgumentException("Unknown sheet: {$sheetName}. Supported: ".implode(', ', array_keys(self::SHEET_COLUMNS)));
        }

        $rows = $this->sheetsService->getRows($sheetName, 2);

        $systemUser = User::where('email', 'akkaoui@crm.test')->first()
            ?? User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->first();

        $imported = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = 2 + $index;

            try {
                $parsed = $this->parseRow($row, $columns);

                if (! $parsed['phone'] && ! $parsed['email']) {
                    $skipped++;

                    continue;
                }

                if (! $this->resolveAgent($parsed['agent'])) {
                    $skipped++;

                    continue;
                }

                if ($this->findExisting($parsed['phone'], $parsed['email'])) {
                    $skipped++;

                    continue;
                }

                $this->leadService->createLead($this->buildLeadData($parsed), $systemUser);
                $imported++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = ['row' => $rowNumber, 'error' => $e->getMessage()];
            }
        }

        return [
            'total' => count($rows),
            'imported' => $imported,
            'skipped' => $skipped,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * Canonical lead concept => every field name we've seen a sheet/webhook
     * use for it so far. The Apps Script's field names are neither fixed
     * nor consistent between calls (French/English, different scripts per
     * sheet), so this is deliberately a growing list rather than a fixed
     * per-sheet column map — add an alias here the first time a new one
     * shows up in a real payload, no other code changes needed.
     */
    protected const WEBHOOK_FIELD_ALIASES = [
        'name' => ['fullname', 'nom_complet', 'name', 'full_name'],
        'email' => ['email'],
        'phone' => ['phone', 'numero_de_telephone', 'phone_number'],
        'postal' => ['postal', 'code_postal'],
        'address' => ['address'],
        'source' => ['source'],
        'agent' => ['agent', 'new_agent'],
        'insurance_type' => ['insurance_type', "type_d'assurance"],
    ];

    /**
     * Pure metadata about the webhook call itself — never persisted:
     * `doublon`/`action`/`old_agent`/`row` don't gate or record anything
     * structurally. Dedupe-by-phone/email already decides create vs.
     * update, and reassignment is driven by comparing the resolved agent
     * against the lead's own current assigned_to (via LeadService::assign(),
     * which already correctly derives "who it belonged to before" from our
     * own data — visible in the History tab). These fields exist purely to
     * let the sheet trigger that check; they carry no information we don't
     * already have more reliably ourselves.
     */
    protected const WEBHOOK_META_KEYS = ['sheet', 'row', 'action', 'doublon', 'old_agent', 'new_agent', 'agent', 'date'];

    protected const ENVELOPE_NOISE_KEYS = ['spreadsheet_id', 'spreadsheet_name', 'sent_at'];

    /**
     * Ingest a single webhook call from the Apps Script. Unlike the polling
     * import() (which reads a fixed column position per sheet), this works
     * from an arbitrary flat JSON object: known fields (via the alias map
     * above) are mapped to real Lead columns. Anything left over — a field
     * without a dedicated column yet (currently_insured, plate, etc.) — is
     * merged as proper JSON into Lead::comment, so the frontend can render
     * it as a clean field/value list instead of prose.
     *
     * If the contact (phone/email) already exists for the SAME agent (or no
     * agent resolves), it's a plain update in place. If it already exists
     * for a DIFFERENT agent, the existing lead is left completely
     * untouched — a brand-new, separate Lead is created for the new agent
     * instead, carrying a note about who had it before. This means the same
     * contact can end up with more than one Lead row, each owned by a
     * different agent, rather than one row being silently handed around.
     *
     * @return array{status: string, lead_id?: int}
     */
    public function importFromWebhookPayload(array $payload): array
    {
        $get = function (string $concept) use ($payload) {
            foreach (self::WEBHOOK_FIELD_ALIASES[$concept] ?? [] as $alias) {
                if (array_key_exists($alias, $payload) && $payload[$alias] !== null && $payload[$alias] !== '') {
                    return $payload[$alias];
                }
            }

            return null;
        };

        $phone = preg_replace('/^p:/', '', trim((string) ($get('phone') ?? '')));
        $email = trim((string) ($get('email') ?? ''));

        if (! $phone && ! $email) {
            return ['status' => 'skipped'];
        }

        $sheetName = $payload['sheet'] ?? null;
        $insuranceType = $sheetName === 'Decennale'
            ? 'DECENNALE'
            : $this->mapInsuranceType(trim((string) ($get('insurance_type') ?? '')));

        $name = $this->splitName(trim((string) ($get('name') ?? '')));
        $agentId = $this->resolveAgent(trim((string) ($get('agent') ?? '')));
        $sourceId = $this->resolveSource(trim((string) ($get('source') ?? '')));
        $postal = preg_replace('/^z:/', '', trim((string) ($get('postal') ?? '')));
        $address = trim((string) ($get('address') ?? ''));
        $rawDate = trim((string) ($payload['date'] ?? ''));
        $submittedAt = $rawDate ? $this->parseDate($rawDate) : null;

        $extraFields = $this->extractExtraFields($payload);

        $existing = $this->findExisting($phone ?: null, $email ?: null);

        if ($existing && (! $agentId || $agentId === $existing->assigned_to)) {
            $updates = array_filter([
                'address' => $address ?: null,
                'city' => $postal ?: null,
            ], fn ($value) => $value !== null && $value !== '');

            $mergedExtras = array_merge($this->decodeExtraFields($existing->comment), $extraFields);
            if ($mergedExtras) {
                $updates['comment'] = json_encode($mergedExtras, JSON_UNESCAPED_UNICODE);
            }

            // The submission date is when the lead first entered the sheet —
            // it shouldn't move just because the lead was later updated.
            if (! $existing->lead_submitted_at && $submittedAt) {
                $updates['lead_submitted_at'] = $submittedAt;
            }

            $existing->update($updates);

            return ['status' => 'updated', 'lead_id' => $existing->id];
        }

        // Either a brand-new contact, or an existing one now claimed by a
        // DIFFERENT agent — both create a fresh Lead row. The prior agent's
        // own lead (if any) is never touched.
        $systemUser = User::where('email', 'akkaoui@crm.test')->first()
            ?? User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->first();

        $leadData = [
            'first_name' => $name['first_name'],
            'last_name' => $name['last_name'],
            'phone' => $phone ?: null,
            'email' => $email ?: null,
            'city' => $postal ?: null,
            'address' => $address ?: null,
            'insurance_type' => $insuranceType,
            'assigned_to' => $agentId,
            'lead_source_id' => $sourceId,
            'comment' => $extraFields ? json_encode($extraFields, JSON_UNESCAPED_UNICODE) : null,
            'lead_submitted_at' => $submittedAt,
        ];

        $lead = $this->leadService->createLead($leadData, $systemUser);

        if ($existing && $agentId) {
            $this->attachPriorAgentNote($lead, $existing, $agentId, $payload);
        }

        if ($agentId) {
            $this->notifyAgent($agentId, $lead);
        }

        return ['status' => 'created', 'lead_id' => $lead->id];
    }

    /**
     * Live-notify the agent they've been assigned a lead via the webhook.
     * Only fires for brand-new leads now — an existing contact claimed by a
     * different agent creates a new lead for them too, so this still covers
     * that case; a same-agent update never calls this at all.
     */
    protected function notifyAgent(int $agentId, Lead $lead): void
    {
        $agent = User::find($agentId);
        $agent?->notify(new LeadReceivedNotification($lead));
    }

    /**
     * Attached to the NEW lead (never the old one) when a different agent
     * claims a contact that already exists for someone else — a note that
     * this contact previously belonged to another agent, so anyone looking
     * at the new lead's History tab can see who else has talked to them.
     * Prefers our own tracked data (the existing lead's own assigned_to,
     * most reliable) and only falls back to resolving the sheet's own
     * `old_agent` claim (or storing it as raw text) when the existing lead
     * has no agent of its own to report.
     */
    protected function attachPriorAgentNote(Lead $newLead, Lead $existing, int $agentId, array $payload): void
    {
        $priorAgentId = $existing->assigned_to;
        $priorAgentName = null;

        if (! $priorAgentId) {
            $oldAgentRaw = trim((string) ($payload['old_agent'] ?? ''));
            if ($oldAgentRaw) {
                $priorAgentId = $this->resolveAgent($oldAgentRaw);
                $priorAgentName = $priorAgentId ? null : $oldAgentRaw;
            }
        }

        if (! $priorAgentId && ! $priorAgentName) {
            return;
        }

        $newLead->assignmentHistories()->create([
            'from_user_id' => $priorAgentId,
            'from_agent_name' => $priorAgentName,
            'to_user_id' => $agentId,
            'assigned_by' => null,
        ]);
    }

    /**
     * Every payload field without a dedicated Lead column yet — i.e. not
     * one of the known aliases and not pure webhook/envelope metadata.
     *
     * @return array<string, mixed>
     */
    protected function extractExtraFields(array $payload): array
    {
        $consumedKeys = array_merge(
            self::WEBHOOK_META_KEYS,
            self::ENVELOPE_NOISE_KEYS,
            ...array_values(self::WEBHOOK_FIELD_ALIASES),
        );

        return collect($payload)
            ->except($consumedKeys)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeExtraFields(?string $comment): array
    {
        if (! $comment) {
            return [];
        }

        $decoded = json_decode($comment, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Shared skip/dedupe/assign/create logic for a single already-parsed
     * row, used by both the polling import() loop and importFromWebhook().
     *
     * @return string 'imported'|'skipped'
     */
    protected function processParsedRow(array $parsed, ?User $systemUser): string
    {
        if (strcasecmp(trim($parsed['doublon'] ?? ''), 'DOUBLON') === 0) {
            return 'skipped';
        }

        if (! $parsed['phone'] && ! $parsed['email']) {
            return 'skipped';
        }

        $existing = $this->findExisting($parsed['phone'], $parsed['email']);

        if ($existing) {
            $agentId = $this->resolveAgent($parsed['agent']);
            $updates = $this->extraFieldUpdates($parsed, $existing);

            if ($updates) {
                $existing->update($updates);
            }

            if ($agentId && $agentId !== $existing->assigned_to) {
                $this->leadService->assign($existing, $agentId);

                return 'imported';
            }

            return $updates ? 'imported' : 'skipped';
        }

        $leadData = $this->buildLeadData($parsed);
        $this->leadService->createLead($leadData, $systemUser);

        return 'imported';
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

        $phone = $get($columns['phone'] ?? null) ?: $get($columns['phone_alt'] ?? null);
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
            'status' => $get($columns['status'] ?? null),
            'client_type_source' => $get($columns['client_type_source'] ?? null),
            'legal_form' => $get($columns['legal_form'] ?? null),
            'sector' => $get($columns['sector'] ?? null),
            'employee_count' => $get($columns['employee_count'] ?? null),
            'company_name' => $get($columns['company_name'] ?? null),
            'annual_revenue' => $get($columns['annual_revenue'] ?? null),
            'currently_insured' => $get($columns['currently_insured'] ?? null),
            'switch_reason' => $get($columns['switch_reason'] ?? null),
            'switch_timing' => $get($columns['switch_timing'] ?? null),
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

    /**
     * A décennale (10-year construction) warranty is a professional-insurance
     * product by law, so unless the sheet answer clearly says "individual",
     * assume it's for a company.
     */
    protected function mapClientType(string $raw): string
    {
        $key = strtolower(trim($raw));

        if (str_contains($key, 'particulier') || str_contains($key, 'individu')) {
            return 'INDIVIDUAL';
        }

        return 'PROFESSIONAL';
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

    /**
     * Short abbreviations used in some sheets that should resolve to the
     * same lead source as their full name (e.g. "fb" and "Facebook" are
     * the same source, not two separate ones).
     */
    protected const SOURCE_ALIASES = [
        'fb' => 'Facebook',
        'ig' => 'Instagram',
    ];

    protected function resolveSource(string $sourceName): ?int
    {
        $sourceName = trim($sourceName);
        if (! $sourceName) {
            return null;
        }

        $canonicalName = self::SOURCE_ALIASES[Str::lower($sourceName)] ?? $sourceName;

        if (isset($this->sourceCache[$canonicalName])) {
            return $this->sourceCache[$canonicalName];
        }

        $source = LeadSource::firstOrCreate(
            ['code' => Str::slug($canonicalName)],
            ['name' => ucfirst($canonicalName), 'is_active' => true]
        );

        $this->sourceCache[$canonicalName] = $source->id;

        return $source->id;
    }

    protected function buildLeadData(array $parsed): array
    {
        $name = $this->splitName($parsed['name']);

        $data = [
            'first_name' => $name['first_name'],
            'last_name' => $name['last_name'],
            'phone' => $parsed['phone'] ?: null,
            'email' => $parsed['email'] ?: null,
            'city' => $parsed['postal'] ?: null,
            'address' => $parsed['address'] ?: null,
            'insurance_type' => $parsed['insurance_type'],
            'assigned_to' => $this->resolveAgent($parsed['agent']),
            'lead_source_id' => $this->resolveSource($parsed['source']),
            'comment' => $this->buildComment($parsed),
            'company_status' => $parsed['status'] ?: null,
            'company_legal_form' => $parsed['legal_form'] ?: null,
            'company_sector' => $parsed['sector'] ?: null,
            'company_employee_count' => $parsed['employee_count'] ?: null,
            'company_name' => $parsed['company_name'] ?: null,
            'company_annual_revenue' => $parsed['annual_revenue'] ?: null,
            'lead_submitted_at' => $parsed['date'] ? $this->parseDate($parsed['date']) : null,
        ];

        if ($parsed['insurance_type'] === 'DECENNALE') {
            $data['client_type'] = $this->mapClientType($parsed['client_type_source']);
        }

        return $data;
    }

    /**
     * When a sheet row matches an already-imported lead, patch in any new
     * non-empty structured data instead of leaving the lead frozen at
     * whatever was captured on first import.
     *
     * @return array<string, mixed>
     */
    protected function extraFieldUpdates(array $parsed, ?Lead $existing = null): array
    {
        $updates = array_filter([
            'address' => $parsed['address'] ?: null,
            'company_status' => $parsed['status'] ?: null,
            'company_legal_form' => $parsed['legal_form'] ?: null,
            'company_sector' => $parsed['sector'] ?: null,
            'company_employee_count' => $parsed['employee_count'] ?: null,
            'company_name' => $parsed['company_name'] ?: null,
            'company_annual_revenue' => $parsed['annual_revenue'] ?: null,
        ], fn ($value) => $value !== null);

        if ($parsed['insurance_type'] === 'DECENNALE' && $parsed['client_type_source']) {
            $updates['client_type'] = $this->mapClientType($parsed['client_type_source']);
        }

        // The submission date is when the lead first entered the sheet — it
        // shouldn't move just because the lead was later reassigned/updated.
        if ($existing && ! $existing->lead_submitted_at && $parsed['date']) {
            $submittedAt = $this->parseDate($parsed['date']);
            if ($submittedAt) {
                $updates['lead_submitted_at'] = $submittedAt;
            }
        }

        return $updates;
    }

    /**
     * Fields with no dedicated Lead column (the Detailles sheet's qualifying
     * questions) get folded into the free-text comment instead.
     */
    protected function buildComment(array $parsed): ?string
    {
        $details = array_filter([
            'currently_insured' => $parsed['currently_insured'] ?: null,
            'switch_reason' => $parsed['switch_reason'] ?: null,
            'switch_timing' => $parsed['switch_timing'] ?: null,
        ], fn ($value) => $value !== null);

        return $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null;
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
