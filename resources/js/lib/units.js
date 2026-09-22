import { CircleCheck, CircleSlash, Wrench } from 'lucide-vue-next';
import { localDate } from '@/lib/licenses';

/**
 * Valores del enum `status` de units (en inglés, como en la base) → etiqueta,
 * icono y colores. El orden es el que se muestra en pantalla.
 */
export const UNIT_STATUS = {
    active: {
        label: 'Activa',
        plural: 'Activas',
        icon: CircleCheck,
        dot: 'bg-emerald-500',
        tone: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-400/10 dark:text-emerald-300 dark:ring-emerald-400/25',
    },
    maintenance: {
        label: 'Mantenimiento',
        plural: 'En mantenimiento',
        icon: Wrench,
        dot: 'bg-amber-500',
        tone: 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/25',
    },
    inactive: {
        label: 'Inactiva',
        plural: 'Inactivas',
        icon: CircleSlash,
        dot: 'bg-slate-400',
        tone: 'bg-slate-50 text-slate-600 ring-slate-500/15 dark:bg-white/[0.05] dark:text-brand-gray dark:ring-white/10',
    },
};

const NEUTRAL_TONE = 'bg-slate-50 text-slate-600 ring-slate-500/15 dark:bg-white/[0.05] dark:text-brand-gray dark:ring-white/10';

/** Días antes del pago en que la unidad se marca por vencer. Igual que Unit::WARNING_DAYS. */
export const PAYMENT_WARNING_DAYS = 30;

/** Un valor que no está en el mapa sale tal cual, en gris. */
export function unitStatus(value) {
    return UNIT_STATUS[value] ?? { label: value, tone: NEUTRAL_TONE };
}

/** «2026-01-15» → «15 ene 2026»: el día importa, ahí vence el pago. */
export function shortDate(iso) {
    if (!iso) return null;

    const label = localDate(iso).toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric' });

    return label[0].toUpperCase() + label.slice(1);
}

/** «12 oct 2025 – 12 abr 2026». Sin fechas no hay rango que mostrar. */
export function periodLabel(payment) {
    const from = shortDate(payment?.starts_on);
    const to = shortDate(payment?.ends_on);

    if (!from && !to) return '—';

    return from && to ? `${from} – ${to}` : (from ?? to);
}

/** Días de hoy a esa fecha: negativo si ya pasó. */
export function daysUntil(iso) {
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    return Math.round((localDate(iso) - today) / 86_400_000);
}

/**
 * Cómo va ese pago según el cierre de su semestre: etiqueta y colores.
 *
 * En la tabla no se muestra —ahí solo importa el periodo—, pero en el detalle
 * y al editar sí: se está decidiendo sobre esas fechas.
 */
export function paymentCountdown(iso) {
    if (!iso) return null;

    const days = daysUntil(iso);

    if (days < 0) {
        return {
            label: days === -1 ? 'Venció ayer' : `Venció hace ${-days} días`,
            tone: 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-400/10 dark:text-red-300 dark:ring-red-400/25',
        };
    }

    if (days === 0) {
        return {
            label: 'Vence hoy',
            tone: 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/25',
        };
    }

    const label = days === 1 ? 'Vence mañana' : `Vence en ${days} días`;

    return {
        label,
        tone:
            days <= PAYMENT_WARNING_DAYS
                ? 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/25'
                : 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-400/10 dark:text-emerald-300 dark:ring-emerald-400/25',
    };
}

/** Pesos mexicanos, siempre con centavos: los importes se comparan de un vistazo. */
export function money(amount) {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount ?? 0);
}
