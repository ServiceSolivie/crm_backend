<?php

namespace App\Filters;

use App\Enums\ActivityCategoryEnum;
use App\Enums\ActivityStateEnum;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filters of the activity journal (the operations).
 *
 *   GET /activity-operations?category[]=payment&category[]=refund   ("refund" = operations containing a refund)
 *   GET /activity-operations?state[]=failure&state[]=warning
 *   GET /activity-operations?problems=1            (a warning or a failure somewhere in it)
 *   GET /activity-operations?event[]=refund.failed (operations containing that event)
 *   GET /activity-operations?actor_id=12 / ?actor_type=client
 *   GET /activity-operations?lead_id=33509
 *   GET /activity-operations?search=PAY-33509      (reference, title, lead)
 *   GET /activity-operations?from=2026-10-01&to=2026-10-07   (last activity, inclusive)
 */
class ActivityOperationFilter extends QueryFilter
{
    /** Query keys ignored by this instance (the chip counters ignore the category) */
    protected array $skipped = [];

    /**
     * A copy of these filters that ignores some keys.
     *
     * @param  array<int, string>  $keys
     */
    public function without(array $keys): static
    {
        $clone = clone $this;
        $clone->skipped = $keys;

        return $clone;
    }

    protected function filters(): array
    {
        return array_diff_key(parent::filters(), array_flip($this->skipped));
    }

    protected function isInternal(string $method): bool
    {
        return parent::isInternal($method) || in_array($method, ['without', 'problemScope'], true);
    }

    protected function category(string|array $value): void
    {
        $values = $this->values($value);
        $withRefund = in_array(ActivityCategoryEnum::REFUND->value, $values, true);
        $categories = array_values(array_diff($values, [ActivityCategoryEnum::REFUND->value]));

        if (! $withRefund && ! $categories) {
            return;
        }

        $this->builder->where(function (Builder $query) use ($withRefund, $categories) {
            if ($categories) {
                $query->whereIn('category', $categories);
            }
            if ($withRefund) {
                $query->orWhere('has_refund', true);
            }
        });
    }

    protected function state(string|array $value): void
    {
        $this->whereIn('state', $value);
    }

    protected function problems(string $value): void
    {
        if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            self::problemScope($this->builder);
        }
    }

    protected function event(string|array $value): void
    {
        $events = $this->values($value);

        if ($events) {
            $this->builder->whereHas('logs', fn (Builder $logs) => $logs->whereIn('event', $events));
        }
    }

    protected function actorId(string $value): void
    {
        $this->builder->whereHas('logs', fn (Builder $logs) => $logs->where('actor_id', $value));
    }

    protected function actorType(string|array $value): void
    {
        $types = $this->values($value);

        if ($types) {
            $this->builder->whereHas('logs', fn (Builder $logs) => $logs->whereIn('actor_type', $types));
        }
    }

    protected function leadId(string $value): void
    {
        $this->builder->where('lead_id', $value);
    }

    protected function search(string $value): void
    {
        $value = trim($value);

        $this->builder->where(function (Builder $query) use ($value) {
            $query->where('reference', 'like', "%{$value}%")
                ->orWhere('title', 'like', "%{$value}%")
                ->orWhere('subtitle', 'like', "%{$value}%")
                ->orWhereHas('lead', fn (Builder $lead) => $lead
                    ->where('reference', 'like', "%{$value}%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$value}%"]));
        });
    }

    protected function from(string $value): void
    {
        if (strtotime($value) !== false) {
            $this->builder->whereDate('last_activity_at', '>=', $value);
        }
    }

    protected function to(string $value): void
    {
        if (strtotime($value) !== false) {
            $this->builder->whereDate('last_activity_at', '<=', $value);
        }
    }

    /**
     * Operations a person should look at: a warning or a failure logged in
     * it, or standing in such a state.
     */
    public static function problemScope(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('problems_count', '>', 0)
            ->orWhereIn('state', [ActivityStateEnum::WARNING->value, ActivityStateEnum::FAILURE->value]));
    }

    /** @return array<int, string> */
    protected function sortable(): array
    {
        return ['last_activity_at', 'started_at'];
    }
}
