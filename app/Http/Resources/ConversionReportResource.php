<?php

namespace App\Http\Resources;

use App\Support\InsuranceTypeCatalog;
use Illuminate\Http\Request;

class ConversionReportResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $total = (int) $this->total;
        $validated = (int) $this->validated;

        $dimensionName = $this->dimension_name
            ?? app(InsuranceTypeCatalog::class)->label((string) $this->dimension_id)
            ?? (string) $this->dimension_id;

        return [
            'dimension_id' => $this->dimension_id,
            'dimension_name' => $dimensionName,
            'total_leads' => $total,
            'validated_leads' => $validated,
            'conversion_rate' => $total > 0 ? round(($validated / $total) * 100, 2) : 0.0,
        ];
    }
}
