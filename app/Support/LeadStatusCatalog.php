<?php

namespace App\Support;

use App\Enums\LeadStatusEnum;
use App\Models\LeadStatus;

/**
 * Every status a lead can have: the system ones (LeadStatusEnum, which carry
 * the business rules) and the custom ones the super admin adds from the CRM.
 * A custom status is only a label and a place in the pipeline; no rule ever
 * looks at it.
 */
class LeadStatusCatalog
{
    /** Stages a custom status may be placed in; "new" and "won" belong to the system */
    public const CUSTOM_STAGES = ['contact', 'follow_up', 'quote', 'lost'];

    /**
     * Custom statuses keyed by code, read once per request.
     *
     * @var array<string, array{label: string, stage: string, is_active: bool}>|null
     */
    private ?array $custom = null;

    /**
     * @return array<string, array{label: string, stage: string, is_active: bool}>
     */
    public function custom(): array
    {
        return $this->custom ??= LeadStatus::query()
            ->orderBy('label')
            ->get(['code', 'label', 'stage', 'is_active'])
            ->mapWithKeys(fn (LeadStatus $status) => [$status->code => [
                'label' => $status->label,
                'stage' => $status->stage,
                'is_active' => $status->is_active,
            ]])
            ->all();
    }

    /**
     * @param  array<string, array{label: string, stage: string, is_active: bool}>  $custom
     */
    public function setCustom(array $custom): void
    {
        $this->custom = $custom;
    }

    public function forget(): void
    {
        $this->custom = null;
    }

    public function isCustom(?string $code): bool
    {
        return $code !== null && LeadStatusEnum::tryFrom($code) === null && isset($this->custom()[$code]);
    }

    public function exists(?string $code): bool
    {
        return $code !== null && (LeadStatusEnum::tryFrom($code) !== null || isset($this->custom()[$code]));
    }

    /**
     * Can a lead be moved to this status now? A deactivated custom status
     * stays on the leads that have it but is no longer offered.
     */
    public function selectable(?string $code): bool
    {
        if ($code === null) {
            return false;
        }

        return LeadStatusEnum::tryFrom($code) !== null || ($this->custom()[$code]['is_active'] ?? false);
    }

    public function label(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        // A status deleted from the table still reads as its code rather than as nothing
        return LeadStatusEnum::tryFrom($code)?->label() ?? $this->custom()[$code]['label'] ?? $code;
    }

    public function stage(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        return LeadStatusEnum::tryFrom($code)?->stage() ?? $this->custom()[$code]['stage'] ?? null;
    }

    /**
     * System statuses first, then the custom ones (deactivated included:
     * leads may still be in them).
     *
     * @return array<int, string>
     */
    public function codes(): array
    {
        return [...LeadStatusEnum::values(), ...array_map('strval', array_keys($this->custom()))];
    }

    /**
     * @return array<int, string>
     */
    public function codesForStage(string $stage): array
    {
        $custom = array_keys(array_filter($this->custom(), fn (array $status) => $status['stage'] === $stage));

        return [...LeadStatusEnum::valuesForStage($stage), ...array_map('strval', $custom)];
    }
}
