<?php

namespace App\Http\Requests\LeadExport;

use App\Enums\PermissionEnum;
use App\Services\ImportedLeadsExportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ImportedLeadsExportRequest extends FormRequest
{
    /** Longest custom range, in days */
    public const MAX_DAYS = 366;

    /**
     * Super admin, or a supervisor for his own team: refused before the
     * input is even looked at.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) ($user?->can(PermissionEnum::LEADS_EXPORT_IMPORTED->value) || $user?->isSupervisor());
    }

    /**
     * The team the export is limited to: none for the super admin, the
     * supervisor's own team otherwise (0 when he has none: nothing).
     */
    public function teamId(): ?int
    {
        $user = $this->user();

        return $user->can(PermissionEnum::LEADS_EXPORT_IMPORTED->value) ? null : (int) ($user->team_id ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'period' => ['required', Rule::in(ImportedLeadsExportService::PERIODS)],
            // Any day inside the week or month wanted
            'date' => ['required_unless:period,custom', 'nullable', 'date_format:Y-m-d'],
            'from' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d'],
            'to' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'origin' => ['nullable', Rule::in(ImportedLeadsExportService::ORIGINS)],
        ];
    }

    /**
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->input('period') !== 'custom' || $validator->errors()->isNotEmpty()) {
                return;
            }

            $days = CarbonImmutable::parse($this->input('from'))->diffInDays(CarbonImmutable::parse($this->input('to'))) + 1;
            if ($days > self::MAX_DAYS) {
                $validator->errors()->add('to', 'La période ne peut pas dépasser '.self::MAX_DAYS.' jours.');
            }
        }];
    }

    public function origin(): string
    {
        return $this->validated('origin') ?? 'all';
    }
}
