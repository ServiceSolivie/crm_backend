<?php

namespace App\Http\Requests\AppointmentReminder;

use App\Enums\ReminderChannelEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppointmentReminderRequest extends FormRequest
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
            'remind_at' => ['sometimes', 'date'],
            'channel' => ['sometimes', Rule::in(ReminderChannelEnum::values())],
        ];
    }
}
