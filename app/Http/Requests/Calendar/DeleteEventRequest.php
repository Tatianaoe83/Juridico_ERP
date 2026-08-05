<?php

namespace App\Http\Requests\Calendar;

use App\Services\Calendar\CalendarAccess;
use Illuminate\Foundation\Http\FormRequest;

class DeleteEventRequest extends FormRequest
{
    /** Mismo criterio que el alta: dueño, o invitado con rol `write`. */
    public function authorize(): bool
    {
        return (bool) app(CalendarAccess::class)
            ->resolve($this->user(), $this->integer('calendario') ?: null)
            ?->canWrite();
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
