<?php

namespace App\Services;

use App\Enums\InsuranceTypeEnum;
use App\Enums\PermissionEnum;
use App\Exceptions\ApiException;
use App\Models\Contract;
use App\Models\DvcType;
use App\Models\DvcTypeVersion;
use App\Models\Lead;
use App\Models\User;
use App\Repositories\Contracts\ContractRepositoryInterface;
use App\Support\Dvc\DvcFieldRules;
use App\Support\Dvc\DvcPrefill;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Contracts are the generated DVCs. A template is an active DVC type, keyed
 * "<insurance type>_dvc" (DvcType::templateKey()), whose form and PDF come
 * from the JSON schema of its current version.
 */
class ContractService
{
    public function __construct(
        protected ContractRepositoryInterface $contracts,
        protected LeadDvcStatus $dvcStatus,
        protected DvcPdfRenderer $renderer,
        protected DvcPrefill $prefill,
        protected DvcFieldRules $fieldRules,
    ) {}

    /**
     * The active DVC types an agent can pick, with the schema of their
     * current version.
     *
     * @return array<int, array<string, mixed>>
     */
    public function templates(): array
    {
        return DvcType::query()
            ->with('currentVersion')
            ->where('is_active', true)
            ->whereNotNull('current_version_id')
            ->orderBy('insurance_type')
            ->get()
            ->map(fn (DvcType $type) => [
                'key' => $type->templateKey(),
                'label' => $type->label,
                'insurance_type' => $type->insurance_type->value,
                'insurance_type_label' => $type->insurance_type->label(),
                'filename_prefix' => $type->filename_prefix,
                'version_id' => $type->currentVersion->id,
                'version' => $type->currentVersion->version,
                'schema' => $type->currentVersion->schema,
            ])
            ->all();
    }

    /**
     * Paginate contracts visible to the given user.
     */
    public function paginateForUser(User $user, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->contracts->paginateScoped($filters, $perPage, $this->visibilityScope($user));
    }

    /**
     * Server-resolved prefill values for the generation form.
     *
     * @return array<string, mixed>
     */
    public function prefill(string $templateKey, ?Lead $lead, User $agent): array
    {
        $type = $this->dvcType($templateKey);

        return $this->prefill->values($type->currentVersion->dvcSchema(), $lead, $agent);
    }

    /**
     * Validate the values against the DVC type's schema, render the PDF,
     * store it and record the contract. Every generation creates a new file +
     * row — previous versions are never touched.
     *
     * $versionId is the schema version the form was opened with: if the super
     * admin published a new one meanwhile, the DVC still matches its form.
     */
    public function generate(string $templateKey, array $data, ?Lead $lead, User $user, ?int $versionId = null): Contract
    {
        $type = $this->dvcType($templateKey);
        $schemaVersion = $versionId !== null
            ? DvcTypeVersion::query()->where('dvc_type_id', $type->id)->find($versionId)
            : $type->currentVersion;

        if (! $schemaVersion) {
            throw new ApiException("Cette version du DVC n'existe pas pour ce type.", 422);
        }

        $schema = $schemaVersion->dvcSchema();

        // Whitelist input to the schema's declared fields, then check them
        $data = array_intersect_key($data, array_flip($schema->dataKeys()));

        $validator = Validator::make($data, $this->fieldRules->rules($schema), [], $this->fieldRules->attributes($schema));
        if ($validator->fails()) {
            throw ValidationException::withMessages(
                collect($validator->errors()->messages())->mapWithKeys(fn ($messages, $key) => ["data.{$key}" => $messages])->all(),
            );
        }

        $clientName = trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''))
            ?: ($data['company_name'] ?? null)
            ?: 'Client';

        return DB::transaction(function () use ($type, $schemaVersion, $schema, $data, $lead, $user, $clientName) {
            $version = $this->contracts->countForLeadAndTemplate($lead?->id, $type->templateKey()) + 1;
            $reference = $this->generateReference();
            $generatedAt = now();

            $output = $this->renderer->render($schema, $data, $reference, $version, $generatedAt);

            $disk = config('contracts.storage_disk', 'local');
            $path = config('contracts.storage_path', 'contracts')."/{$generatedAt->format('Y/m')}/{$reference}.pdf";
            Storage::disk($disk)->put($path, $output);

            $filename = sprintf(
                '%s_%s_%s_v%d.pdf',
                $type->filename_prefix,
                Str::slug($clientName) ?: 'client',
                $generatedAt->format('Y-m-d'),
                $version,
            );

            /** @var Contract $contract */
            $contract = $this->contracts->create([
                'reference' => $reference,
                'template_key' => $type->templateKey(),
                'version' => $version,
                'dvc_type_version_id' => $schemaVersion->id,
                'lead_id' => $lead?->id,
                'client_name' => $clientName,
                'data' => $data,
                'original_filename' => $filename,
                'file_path' => $path,
                'mime_type' => 'application/pdf',
                'file_size' => strlen($output),
                'generated_by' => $user->id,
            ]);

            // A generated DVC puts the lead "en attente de signature"
            if ($lead) {
                $this->dvcStatus->refresh($lead);
            }

            return $contract->load(['lead:id,reference,first_name,last_name', 'generator:id,name']);
        });
    }

    public function download(Contract $contract): StreamedResponse
    {
        $disk = config('contracts.storage_disk', 'local');

        if (! Storage::disk($disk)->exists($contract->file_path)) {
            throw new ApiException('The contract file is no longer available.', 404);
        }

        return Storage::disk($disk)->download($contract->file_path, $contract->original_filename);
    }

    public function delete(Contract $contract): bool
    {
        Storage::disk(config('contracts.storage_disk', 'local'))->delete($contract->file_path);

        $deleted = $this->contracts->delete($contract->id);

        if ($contract->lead) {
            $this->dvcStatus->refresh($contract->lead);
        }

        return $deleted;
    }

    /**
     * Contracts a user can see: everything with view-all leads permission,
     * own + team-lead contracts for team scope, otherwise only contracts
     * they generated or on leads assigned to them.
     */
    public function visibilityScope(User $user): \Closure
    {
        return function (Builder $query) use ($user) {
            if ($user->can(PermissionEnum::LEADS_VIEW_ALL->value)) {
                return;
            }

            if ($user->can(PermissionEnum::LEADS_VIEW_TEAM->value)) {
                $query->where(function (Builder $query) use ($user) {
                    $query->where('generated_by', $user->id)
                        ->orWhereHas('lead', fn (Builder $leads) => $leads->where('team_id', $user->team_id));
                });

                return;
            }

            $query->where(function (Builder $query) use ($user) {
                $query->where('generated_by', $user->id)
                    ->orWhereHas('lead', fn (Builder $leads) => $leads->where('assigned_to', $user->id));
            });
        };
    }

    /**
     * The active DVC type behind a template key ("auto_dvc" → AUTO).
     */
    protected function dvcType(string $templateKey): DvcType
    {
        $insuranceType = str_ends_with($templateKey, '_dvc')
            ? InsuranceTypeEnum::tryFrom(strtoupper(substr($templateKey, 0, -4)))
            : null;

        $type = $insuranceType
            ? DvcType::query()->with('currentVersion')->where('insurance_type', $insuranceType->value)->first()
            : null;

        if (! $type || ! $type->is_active || ! $type->currentVersion) {
            throw new ApiException("Aucun DVC actif pour ce type : {$templateKey}", 422);
        }

        return $type;
    }

    protected function generateReference(): string
    {
        do {
            $reference = 'CT-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while ($this->contracts->referenceExists($reference));

        return $reference;
    }
}
