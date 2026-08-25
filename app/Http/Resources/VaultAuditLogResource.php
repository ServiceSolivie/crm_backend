<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class VaultAuditLogResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'domain' => $this->domain,
            'ip_address' => $this->ip_address,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'credential' => $this->whenLoaded('credential', fn () => [
                'id' => $this->credential->id,
                'label' => $this->credential->label,
            ]),
            'created_at' => $this->formatDate($this->created_at),
        ];
    }
}
