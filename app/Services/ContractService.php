<?php

namespace App\Services;

use App\Contracts\Templates\ContractTemplate;
use App\Contracts\Templates\ContractTemplateRegistry;
use App\Enums\PermissionEnum;
use App\Exceptions\ApiException;
use App\Models\Contract;
use App\Models\Lead;
use App\Models\User;
use App\Repositories\Contracts\ContractRepositoryInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContractService
{
    public function __construct(
        protected ContractRepositoryInterface $contracts,
        protected ContractTemplateRegistry $registry,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function templates(): array
    {
        return $this->registry->all()
            ->map(fn (ContractTemplate $template) => $template->toArray())
            ->values()
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
        return $this->template($templateKey)->prefill($lead, $agent);
    }

    /**
     * Render the template with the given data, store the PDF, and record
     * the contract. Every generation creates a new file + row — previous
     * versions are never touched.
     */
    public function generate(string $templateKey, array $data, ?Lead $lead, User $user): Contract
    {
        $template = $this->template($templateKey);

        // Whitelist input to the template's declared fields.
        $data = array_intersect_key($data, array_flip($template->fieldKeys()));

        $clientName = trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')) ?: 'Client';

        return DB::transaction(function () use ($template, $data, $lead, $user, $clientName) {
            $version = $this->contracts->countForLeadAndTemplate($lead?->id, $template->key()) + 1;
            $reference = $this->generateReference();
            $generatedAt = now();

            $pdf = Pdf::loadView($template->view(), [
                'broker' => config('contracts.broker'),
                'authority' => config('contracts.authority'),
                'mediation' => config('contracts.mediation'),
                'data' => $data,
                'reference' => $reference,
                'version' => $version,
                'generated_at' => $generatedAt,
            ]);

            $output = $pdf->output();

            $disk = config('contracts.storage_disk', 'local');
            $path = config('contracts.storage_path', 'contracts')."/{$generatedAt->format('Y/m')}/{$reference}.pdf";
            Storage::disk($disk)->put($path, $output);

            $filename = sprintf(
                '%s_%s_%s_v%d.pdf',
                $template->filenamePrefix(),
                Str::slug($clientName) ?: 'client',
                $generatedAt->format('Y-m-d'),
                $version,
            );

            /** @var Contract $contract */
            $contract = $this->contracts->create([
                'reference' => $reference,
                'template_key' => $template->key(),
                'version' => $version,
                'lead_id' => $lead?->id,
                'client_name' => $clientName,
                'data' => $data,
                'original_filename' => $filename,
                'file_path' => $path,
                'mime_type' => 'application/pdf',
                'file_size' => strlen($output),
                'generated_by' => $user->id,
            ]);

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

        return $this->contracts->delete($contract->id);
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

    protected function template(string $key): ContractTemplate
    {
        $template = $this->registry->find($key);

        if (! $template) {
            throw new ApiException("Unknown contract template: {$key}", 422);
        }

        return $template;
    }

    protected function generateReference(): string
    {
        do {
            $reference = 'CT-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while ($this->contracts->referenceExists($reference));

        return $reference;
    }
}
