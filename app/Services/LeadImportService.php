<?php

namespace App\Services;

use App\Enums\InsuranceTypeEnum;
use App\Enums\LeadImportStatusEnum;
use App\Enums\PermissionEnum;
use App\Exceptions\ApiException;
use App\Filters\LeadImportFilter;
use App\Models\LeadImport;
use App\Models\LeadSource;
use App\Models\User;
use App\Repositories\Contracts\LeadImportRepositoryInterface;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class LeadImportService
{
    /**
     * CSV header columns required for every row.
     *
     * @var array<int, string>
     */
    protected const REQUIRED_COLUMNS = ['first_name', 'last_name', 'phone', 'insurance_type'];

    public function __construct(
        protected LeadImportRepositoryInterface $leadImports,
        protected LeadService $leadService,
    ) {}

    public function paginateForUser(User $user, LeadImportFilter $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->leadImports->paginateFiltered($filters, $perPage, $this->visibilityScope($user));
    }

    protected function visibilityScope(User $user): Closure
    {
        return function ($query) use ($user) {
            if ($user->can(PermissionEnum::LEADS_VIEW_ALL->value)) {
                return;
            }

            $query->where('imported_by', $user->id);
        };
    }

    /**
     * Parse the uploaded CSV, create a Lead for every valid row, and record
     * a per-row error report for the rest.
     */
    public function import(UploadedFile $file, User $user): LeadImport
    {
        $rows = $this->readCsv($file);
        $header = array_map(fn ($column) => strtolower(trim((string) $column)), array_shift($rows) ?? []);

        $missing = array_diff(self::REQUIRED_COLUMNS, $header);

        if ($missing !== []) {
            throw new ApiException(
                'CSV is missing required columns: '.implode(', ', $missing),
                422
            );
        }

        /** @var LeadImport $import */
        $import = $this->leadImports->create([
            'file_name' => $file->getClientOriginalName(),
            'imported_by' => $user->id,
            'total_rows' => count($rows),
            'status' => LeadImportStatusEnum::PROCESSING->value,
        ]);

        $errors = [];
        $successCount = 0;

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2;
            $record = $this->mapRow($header, $row);

            $rowErrors = $this->validateRow($record);

            if ($rowErrors !== []) {
                $errors[] = ['row' => $lineNumber, 'errors' => $rowErrors];

                continue;
            }

            $this->leadService->createLead($this->buildLeadData($record, $import), $user);
            $successCount++;
        }

        $status = $successCount > 0 ? LeadImportStatusEnum::COMPLETED : LeadImportStatusEnum::FAILED;

        $errorReportPath = null;

        if ($errors !== []) {
            $errorReportPath = "lead-imports/{$import->id}/errors.json";
            Storage::disk('local')->put($errorReportPath, json_encode($errors, JSON_PRETTY_PRINT));
        }

        $import->update([
            'success_rows' => $successCount,
            'failed_rows' => count($errors),
            'status' => $status->value,
            'error_report_path' => $errorReportPath,
        ]);

        return $import->refresh();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function errors(LeadImport $leadImport): array
    {
        if (! $leadImport->error_report_path || ! Storage::disk('local')->exists($leadImport->error_report_path)) {
            return [];
        }

        return json_decode(Storage::disk('local')->get($leadImport->error_report_path), true) ?? [];
    }

    /**
     * @return array<int, array<int, string>>
     */
    protected function readCsv(UploadedFile $file): array
    {
        $rows = [];
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw new ApiException('Unable to read the uploaded file.', 422);
        }

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, string>  $row
     * @return array<string, string|null>
     */
    protected function mapRow(array $header, array $row): array
    {
        $record = [];

        foreach ($header as $index => $column) {
            $value = $row[$index] ?? null;
            $record[$column] = $value !== null && $value !== '' ? trim((string) $value) : null;
        }

        return $record;
    }

    /**
     * @param  array<string, string|null>  $record
     * @return array<int, string>
     */
    protected function validateRow(array $record): array
    {
        $errors = [];

        foreach (['first_name', 'last_name', 'phone'] as $field) {
            if (empty($record[$field])) {
                $errors[] = "The {$field} field is required.";
            }
        }

        if (empty($record['insurance_type']) || InsuranceTypeEnum::tryFrom($record['insurance_type']) === null) {
            $errors[] = 'The insurance_type field is invalid or missing.';
        }

        if (! empty($record['lead_source_code']) && ! LeadSource::query()->where('code', $record['lead_source_code'])->exists()) {
            $errors[] = "No lead source found with code '{$record['lead_source_code']}'.";
        }

        if (! empty($record['assigned_to_email']) && ! User::query()->where('email', $record['assigned_to_email'])->exists()) {
            $errors[] = "No user found with email '{$record['assigned_to_email']}'.";
        }

        if (! empty($record['team_id']) && ! ctype_digit($record['team_id'])) {
            $errors[] = 'The team_id field must be an integer.';
        }

        return $errors;
    }

    /**
     * @param  array<string, string|null>  $record
     * @return array<string, mixed>
     */
    protected function buildLeadData(array $record, LeadImport $import): array
    {
        $leadSourceId = null;

        if (! empty($record['lead_source_code'])) {
            $leadSourceId = LeadSource::query()->where('code', $record['lead_source_code'])->value('id');
        }

        $assignedTo = null;

        if (! empty($record['assigned_to_email'])) {
            $assignedTo = User::query()->where('email', $record['assigned_to_email'])->value('id');
        }

        return [
            'first_name' => $record['first_name'],
            'last_name' => $record['last_name'],
            'phone' => $record['phone'],
            'email' => $record['email'] ?? null,
            'city' => $record['city'] ?? null,
            'birth_date' => $record['birth_date'] ?? null,
            'lead_source_id' => $leadSourceId,
            'insurance_type' => $record['insurance_type'],
            'assigned_to' => $assignedTo,
            'team_id' => $record['team_id'] ?? null,
            'lead_import_id' => $import->id,
            'comment' => $record['comment'] ?? null,
        ];
    }
}
