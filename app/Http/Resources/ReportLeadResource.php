<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * A lead in the leads report of a supervisor: same as LeadResource,
 * without the money figures (revenue stays personal for him).
 */
class ReportLeadResource extends LeadResource
{
    protected const MONEY_FIELDS = [
        'expected_revenue',
        'total_received',
        'remaining_amount',
        'payment_status',
        'payment_status_label',
        'payments_count',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_diff_key(parent::toArray($request), array_flip(self::MONEY_FIELDS));
    }
}
