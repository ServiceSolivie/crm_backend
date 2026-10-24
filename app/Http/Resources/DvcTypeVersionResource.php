<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * A version of a DVC type's schema. The history list leaves the schema out
 * (withoutSchema()); it is read on its own when needed.
 */
class DvcTypeVersionResource extends BaseResource
{
    protected bool $withSchema = true;

    public function withoutSchema(): static
    {
        $this->withSchema = false;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'version' => $this->version,
            'change_note' => $this->change_note,
            'schema' => $this->when($this->withSchema, $this->schema),
            'contracts_count' => $this->whenCounted('contracts'),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null),
            'created_at' => $this->formatDate($this->created_at),
        ];
    }
}
