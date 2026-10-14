<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class TrustedDeviceResource extends BaseResource
{
    /**
     * The passkey itself (id and public key) is never sent to the page.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $person = fn ($user) => $user ? ['id' => $user->id, 'name' => $user->name] : null;

        return [
            'id' => $this->id,
            'label' => $this->label,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'browser' => $this->browser,
            'registered_by' => $this->whenLoaded('registeredBy', fn () => $person($this->registeredBy)),
            'approved_by' => $this->whenLoaded('approvedBy', fn () => $person($this->approvedBy)),
            'approved_at' => $this->formatDate($this->approved_at),
            'revoked_at' => $this->formatDate($this->revoked_at),
            'revoked_reason' => $this->revoked_reason,
            'last_used_by' => $this->whenLoaded('lastUsedBy', fn () => $person($this->lastUsedBy)),
            'last_used_at' => $this->formatDate($this->last_used_at),
            'last_ip' => $this->last_ip,
            'created_at' => $this->formatDate($this->created_at),
        ];
    }
}
