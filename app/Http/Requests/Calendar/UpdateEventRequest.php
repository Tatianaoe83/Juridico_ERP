<?php

namespace App\Http\Requests\Calendar;

class UpdateEventRequest extends StoreEventRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            // Id de Graph: cadena larga en base64url, no un entero.
            'event_id' => ['required', 'string'],
        ];
    }
}
