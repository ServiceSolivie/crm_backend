<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class TeamResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'manager' => $this->whenLoaded('manager', fn () => $this->manager ? [
                'id' => $this->manager->id,
                'name' => $this->manager->name,
                'email' => $this->manager->email,
            ] : null),
            'leader' => $this->whenLoaded('leader', fn () => $this->leader ? [
                'id' => $this->leader->id,
                'name' => $this->leader->name,
                'email' => $this->leader->email,
            ] : null),
            'members_count' => $this->when(isset($this->members_count), fn () => $this->members_count),
            'members' => $this->whenLoaded('members', fn () => UserResource::collection($this->members)),
            'created_at' => $this->formatDate($this->created_at),
            'updated_at' => $this->formatDate($this->updated_at),
        ];
    }
}
