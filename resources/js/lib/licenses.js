import { CalendarCheck, CalendarClock, CalendarX, Hourglass } from 'lucide-vue-next';

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
    in_progress: {
        label: 'En trámite',
        plural: 'En trámite',
        icon: Hourglass,
        dot: 'bg-brand-light dark:bg-brand-gray',
        tone: 'bg-brand/[0.06] text-brand ring-brand/15 dark:bg-brand-light/25 dark:text-white dark:ring-brand-gray/25',
    },
};

const NEUTRAL_TONE = 'bg-slate-50 text-slate-600 ring-slate-500/15 dark:bg-white/[0.05] dark:text-brand-gray dark:ring-white/10';

/** Un valor que no está en el mapa sale tal cual, en gris. */
export function licenseStatus(value) {
    return LICENSE_STATUS[value] ?? { label: value, tone: NEUTRAL_TONE };
}
