import { CalendarCheck, CalendarClock, CalendarX } from 'lucide-vue-next';

/**
 * Valores del enum `status` de licencias (en inglés, como en la base) →
 * etiqueta, icono y colores. El orden es el que se muestra en pantalla.
 */
export const LICENSE_STATUS = {
    active: {
        label: 'Vigente',
        plural: 'Vigentes',
        icon: CalendarCheck,
        dot: 'bg-emerald-500',
        tone: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-400/10 dark:text-emerald-300 dark:ring-emerald-400/25',
    },
    expiring: {
        label: 'Por vencer',
        plural: 'Por vencer',
        icon: CalendarClock,
        dot: 'bg-amber-500',
        tone: 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/25',
    },
    expired: {
        label: 'Vencido',
        plural: 'Vencidos',
        icon: CalendarX,
        dot: 'bg-red-500',
        tone: 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-400/10 dark:text-red-300 dark:ring-red-400/25',
    },
};

const NEUTRAL_TONE = 'bg-slate-50 text-slate-600 ring-slate-500/15 dark:bg-white/[0.05] dark:text-brand-gray dark:ring-white/10';

/** «2026-09-15» en hora local: con new Date(iso) saldría un día antes. */
export function localDate(iso) {
    const [y, m, d] = iso.split('-').map(Number);

    return new Date(y, m - 1, d);
}

/** Días de hoy a la vigencia: negativo si ya venció. */
export function daysLeft(iso) {
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    return Math.round((localDate(iso) - today) / 86_400_000);
}

/** «Vence en 12 días», «Venció hace 3 días», «Vence hoy». */
export function remainingLabel(iso) {
    const days = daysLeft(iso);

    if (days === 0) return 'Vence hoy';
    if (days > 0) return days === 1 ? 'Vence mañana' : `Vence en ${days} días`;

    return days === -1 ? 'Venció ayer' : `Venció hace ${-days} días`;
}

/** Un valor que no está en el mapa sale tal cual, en gris. */
export function licenseStatus(value) {
    return LICENSE_STATUS[value] ?? { label: value, tone: NEUTRAL_TONE };
}

/**
 * Aviso de vencimiento, en minutos antes del inicio del evento: los mismos
 * que entiende Outlook, que es quien lanza la alerta. Los mismos presets que
 * ofrece Outlook y en las dos variantes, porque el punto de partida cambia:
 * con hora se cuenta desde ella; sin hora el evento arranca a medianoche, así
 * que «el día anterior a las 9:00» son 900 minutos.
 *
 * Los valores tienen que existir en License::REMINDER_MINUTES.
 */
const REMINDERS_TIMED = [
    { value: 0, label: 'En el momento del evento' },
    { value: 5, label: '5 minutos antes' },
    { value: 15, label: '15 minutos antes' },
    { value: 30, label: '30 minutos antes' },
    { value: 60, label: '1 hora antes' },
    { value: 120, label: '2 horas antes' },
    { value: 720, label: '12 horas antes' },
    { value: 1440, label: '1 día antes' },
    { value: 2880, label: '2 días antes' },
    { value: 10080, label: '1 semana antes' },
];

const REMINDERS_ALL_DAY = [
    { value: 0, label: 'El día del evento, a medianoche' },
    { value: 15, label: 'El día anterior, 23:45' },
    { value: 420, label: 'El día anterior, 17:00' },
    { value: 900, label: 'El día anterior, 9:00' },
    { value: 2340, label: '2 días antes, 9:00' },
    { value: 9540, label: '1 semana antes, 9:00' },
    { value: 10080, label: '1 semana antes, a medianoche' },
];

/** Opciones según el evento: con hora o de todo el día. */
export function reminderOptions(hasTime) {
    return hasTime ? REMINDERS_TIMED : REMINDERS_ALL_DAY;
}

/** Etiqueta de un aviso guardado; null = ninguno. */
export function reminderLabel(minutes, hasTime) {
    if (minutes === null || minutes === undefined) return 'Sin aviso';

    return reminderOptions(hasTime).find((option) => option.value === minutes)?.label ?? `${minutes} minutos antes`;
}
