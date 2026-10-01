<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

/**
 * Filters of the Payments page (payment links of every visible lead).
 *
 *   GET /payment-sessions?search=PAY-33505       (reference, e-mail, Hyperswitch id, lead)
 *   GET /payment-sessions?status[]=PAYEE&status[]=ECHOUEE
 *   GET /payment-sessions?created_by=12           (agent who sent the link)
 *   GET /payment-sessions?from=2026-09-01&to=2026-09-30   (sent between, inclusive)
 *   GET /payment-sessions?sort=-amount            (created_at, amount, reference, status, paid_at)
 */
class PaymentSessionFilter extends QueryFilter
{
    protected function search(string $value): void
    {
        $value = trim($value);

        $this->builder->where(function (Builder $query) use ($value) {
            $query->where('reference', 'like', "%{$value}%")
                ->orWhere('client_email', 'like', "%{$value}%")
                ->orWhere('hyperswitch_payment_id', 'like', "%{$value}%")
                ->orWhereHas('lead', fn (Builder $lead) => $lead
                    ->where('reference', 'like', "%{$value}%")
                    ->orWhere('first_name', 'like', "%{$value}%")
                    ->orWhere('last_name', 'like', "%{$value}%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$value}%"])
                    ->orWhere('phone', 'like', "%{$value}%"));
        });
    }

    protected function status(string|array $value): void
    {
        $this->whereIn('status', $value);
    }

    protected function createdBy(string|array $value): void
    {
        $this->whereIn('created_by', $value);
    }

    protected function from(string $value): void
    {
        if ($this->isDate($value)) {
            $this->builder->whereDate('created_at', '>=', $value);
        }
    }

    protected function to(string $value): void
    {
        if ($this->isDate($value)) {
            $this->builder->whereDate('created_at', '<=', $value);
        }
    }

    protected function sortable(): array
    {
        return ['created_at', 'amount', 'reference', 'status', 'paid_at'];
    }

    /**
     * A day as YYYY-MM-DD (anything else is ignored).
     */
    protected function isDate(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
    }
}
