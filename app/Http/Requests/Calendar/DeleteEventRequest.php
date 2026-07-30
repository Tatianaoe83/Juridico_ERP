<?php

namespace App\Http\Requests\Calendar;

use Illuminate\Foundation\Http\FormRequest;

class DeleteEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->microsoftAccount?->canWriteCalendar();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event_id' => ['required', 'string'],
        ];
    }
}
