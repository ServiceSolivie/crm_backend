<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Filters\CampaignFilter;
use App\Models\Campaign;
use App\Repositories\Contracts\CampaignRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CampaignService
{
    public function __construct(protected CampaignRepositoryInterface $campaigns) {}

    public function paginate(CampaignFilter $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->campaigns->paginateFiltered($filters, $perPage);
    }

    public function create(array $attributes): Campaign
    {
        $attributes['is_active'] = $attributes['is_active'] ?? true;

        return $this->campaigns->create($attributes)->load('leadSource');
    }

    public function update(Campaign $campaign, array $attributes): Campaign
    {
        $campaign->update($attributes);

        return $campaign->refresh()->load('leadSource');
    }

    public function delete(Campaign $campaign): bool
    {
        if ($campaign->leads()->exists() || $campaign->touchpoints()->exists()) {
            throw new ApiException('This campaign has acquisitions and cannot be deleted.', 409);
        }

        return (bool) $campaign->delete();
    }
}
