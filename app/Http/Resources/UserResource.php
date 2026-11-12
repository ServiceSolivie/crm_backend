<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class UserResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
            'team' => $this->whenLoaded('team', fn () => $this->team ? [
                'id' => $this->team->id,
                'name' => $this->team->name,
            ] : null),
            // Marketing only: sources the user's data is limited to (empty = all)
            'lead_sources' => $this->whenLoaded('leadSources', fn () => $this->leadSources
                ->map(fn ($source) => ['id' => $source->id, 'name' => $source->name])
                ->values()),
            'ringover' => [
                'is_linked' => $this->isRingoverLinked(),
                'user_id' => $this->ringover_user_id,
                'number' => $this->ringover_number,
                'linked_at' => $this->formatDate($this->ringover_linked_at),
            ],
            'last_login_at' => $this->formatDate($this->last_login_at),
            'created_at' => $this->formatDate($this->created_at),
            'updated_at' => $this->formatDate($this->updated_at),
        ];
    }
}
