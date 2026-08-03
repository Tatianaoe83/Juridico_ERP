<?php

namespace App\Http\Requests\Calendar;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class StoreEventRequest extends FormRequest
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
        $allDay = $this->boolean('all_day');

        return [
            'title' => ['required', 'string', 'max:255'],
            'all_day' => ['boolean'],
            'starts_at' => ['required', 'date'],
            // Un evento de todo el día puede empezar y terminar el mismo día;
            // uno con hora no.
            'ends_at' => ['required', 'date', $allDay ? 'after_or_equal:starts_at' : 'after:starts_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            // Outlook acepta más, pero pasado cierto punto la invitación se
            // trata como correo masivo y acaba en spam.
            'attendees' => ['sometimes', 'array', 'max:50'],
            'attendees.*' => ['email'],
            // Suma a los invitados a quienes tienen acceso al calendario, para
            // que Outlook les mande invitación, actualización y cancelación.
            'invite_shared' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ends_at.after' => 'La hora de fin debe ser posterior a la de inicio.',
            'ends_at.after_or_equal' => 'La fecha de fin no puede ser anterior a la de inicio.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'título',
            'starts_at' => 'inicio',
            'ends_at' => 'fin',
            'location' => 'ubicación',
            'description' => 'descripción',
            'attendees' => 'invitados',
            'attendees.*' => 'correo del invitado',
        ];
    }

    /**
     * Lo que espera MicrosoftGraph::createEvent().
     *
     * @return array{title: string, description: ?string, location: ?string, all_day: bool, start: Carbon, end: Carbon, attendees: array<int, string>}
     */
    public function event(): array
    {
        return [
            'title' => $this->string('title')->trim()->value(),
            'description' => $this->input('description'),
            'location' => $this->input('location'),
            'all_day' => $this->boolean('all_day'),
            'start' => Carbon::parse($this->input('starts_at')),
            'end' => Carbon::parse($this->input('ends_at')),
            'attendees' => collect($this->input('attendees', []))
                ->map(fn (string $email) => Str::lower(trim($email)))
                ->filter()
                ->unique()
                ->values()
                ->all(),
        ];
    }
}
