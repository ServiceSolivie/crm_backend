<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class BaseResource extends JsonResource
{
    /**
     * Format a date/datetime attribute as ISO-8601, or null if absent.
     */
    protected function formatDate(mixed $date): ?string
    {
        return $date ? Carbon::parse($date)->toIso8601String() : null;
    }
}
