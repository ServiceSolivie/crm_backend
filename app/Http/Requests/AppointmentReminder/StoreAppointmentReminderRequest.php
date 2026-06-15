<?php

namespace App\Http\Requests\AppointmentReminder;

use App\Enums\ReminderChannelEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'remind_at' => ['required', 'date'],
            'channel' => ['required', Rule::in(ReminderChannelEnum::values())],
        ];
    }
}
