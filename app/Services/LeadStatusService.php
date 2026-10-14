<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\LeadStatus;
use App\Models\User;
use App\Repositories\Contracts\LeadStatusRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class LeadStatusService
{
    public function __construct(protected LeadStatusRepositoryInterface $leadStatuses) {}

    public function listCustom(): Collection
    {
        return $this->leadStatuses->allWithLeadCounts();
    }

    public function createLeadStatus(array $attributes, User $creator): LeadStatus
    {
        $attributes['is_active'] = $attributes['is_active'] ?? true;
        $attributes['created_by'] = $creator->id;

        return $this->leadStatuses->create($attributes)->loadCount('leads');
    }

    /**
     * Label, stage and active flag only: the code is what leads store, it
     * never changes.
     */
    public function updateLeadStatus(LeadStatus $leadStatus, array $attributes): LeadStatus
    {
        unset($attributes['code']);

        $leadStatus->update($attributes);

        return $leadStatus->refresh()->loadCount('leads');
    }

    public function deleteLeadStatus(LeadStatus $leadStatus): bool
    {
        if ($this->leadStatuses->isUsed($leadStatus->code)) {
            throw new ApiException('Ce statut est utilisé par des leads ou figure dans leur historique : désactivez-le au lieu de le supprimer.', 409);
        }

        return (bool) $leadStatus->delete();
    }
}
