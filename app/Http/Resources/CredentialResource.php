<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class CredentialResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'is_active' => $this->is_active,
            'has_username' => filled($this->username_encrypted),
            'has_email' => filled($this->email_encrypted),
            'has_password' => filled($this->password_encrypted),
            'partner' => $this->whenLoaded('partner', fn () => [
                'id' => $this->partner->id,
                'name' => $this->partner->name,
            ]),
            'created_at' => $this->formatDate($this->created_at),
            'updated_at' => $this->formatDate($this->updated_at),
        ];
    }
}
