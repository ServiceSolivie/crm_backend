<?php

namespace App\Repositories\Eloquent;

use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\LeadStatusHistory;
use App\Repositories\Contracts\LeadStatusRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class LeadStatusRepository extends BaseRepository implements LeadStatusRepositoryInterface
{
    public function model(): string
    {
        return LeadStatus::class;
    }

    public function allWithLeadCounts(): Collection
    {
        return $this->newQuery()->withCount('leads')->orderBy('label')->get();
    }

    public function isUsed(string $code): bool
    {
        return Lead::withTrashed()->where('status', $code)->exists()
            || LeadStatusHistory::where('to_status', $code)->orWhere('from_status', $code)->exists();
    }
}
