<?php

namespace App\Filters;

/**
 * Supported query parameters:
 *
 *   GET /appointments/{appointment}/reminders?filter[channel]=email
 *   GET /appointments/{appointment}/reminders?filter[sent]=0
 *   GET /appointments/{appointment}/reminders?sort=remind_at
 */
class AppointmentReminderFilter extends QueryFilter
{
    protected function channel(string $value): void
    {
        $this->builder->where('channel', $value);
    }

    protected function sent(string $value): void
    {
        if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            $this->builder->whereNotNull('sent_at');
        } else {
            $this->builder->whereNull('sent_at');
        }
    }

    /**
     * @return array<int, string>
     */
    protected function sortable(): array
    {
        return [
            'remind_at',
            'created_at',
        ];
    }
}
