/**
 * Formateo compartido de los eventos que devuelve MicrosoftGraph.
 *
 * Vive aquí y no en cada página porque el calendario y la vista de compartir
 * pintan los mismos datos: duplicar los colores de RSVP garantiza que un día
 * dejen de coincidir.
 */

/** Estados que devuelve Graph en attendees[].status.response. */
const RESPONSES = {
    accepted: { label: 'Aceptó', dot: 'bg-emerald-500' },
    declined: { label: 'Rechazó', dot: 'bg-red-500' },
    tentativelyAccepted: { label: 'Quizá', dot: 'bg-amber-500' },
    organizer: { label: 'Organizador', dot: 'bg-[#459AF7]' },
    none: { label: 'Sin responder', dot: 'bg-muted-foreground/40' },
};

/** Graph entrega '2026-08-05T16:00:00.0000000'; se corta, no se parsea. */
const datePart = (iso) => (iso ?? '').slice(0, 10);
const timePart = (iso) => (iso ?? '').slice(11, 16);

export function useEventDisplay() {
    /** Invitados sin el organizador, que siempre se incluye a sí mismo. */
    function guests(event) {
        return (event.attendees ?? []).filter((attendee) => attendee.response !== 'organizer');
    }

    /** Con fallback: un estado nuevo de Graph no debe romper la vista. */
    function response(guest) {
        return RESPONSES[guest.response] ?? RESPONSES.none;
    }

    /** 'mié 5 ago' */
    function dayLabel(iso) {
        const [year, month, day] = datePart(iso).split('-').map(Number);

        if (!year) return '';

        return new Date(year, month - 1, day).toLocaleDateString('es-MX', {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
        });
    }

    /** 'miércoles 5 de agosto' — para el tooltip, donde sí cabe. */
    function longDayLabel(iso) {
        const [year, month, day] = datePart(iso).split('-').map(Number);

        if (!year) return '';

        return new Date(year, month - 1, day).toLocaleDateString('es-MX', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
        });
    }

    /** '16:00 – 17:30' o 'Todo el día' */
    function timeLabel(event) {
        return event.all_day ? 'Todo el día' : `${timePart(event.start)} – ${timePart(event.end)}`;
    }

    /** Solo la hora de inicio: es lo único que cabe en una celda del mes. */
    function startLabel(event) {
        return event.all_day ? 'Todo el día' : timePart(event.start);
    }

    return { RESPONSES, guests, response, dayLabel, longDayLabel, timeLabel, startLabel };
}
