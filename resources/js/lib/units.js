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

/** Cómo va un pago. Igual que UnitPolicy::paymentStatus() y annualStatus(). */
export const PAYMENT_STATUS = {
    paid: {
        label: 'Al día',
        tone: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-400/10 dark:text-emerald-300 dark:ring-emerald-400/25',
    },
    pending: {
        label: 'Pendiente',
        tone: 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/25',
    },
    overdue: {
        label: 'Vencido',
        tone: 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-400/10 dark:text-red-300 dark:ring-red-400/25',
    },
};

/** Sin póliza no hay pagos que mostrar: la unidad queda por asignar. */
export const UNASSIGNED = 'Por asignar';

/** Días antes del pago en que la unidad se marca por vencer. Igual que Unit::WARNING_DAYS. */
export const PAYMENT_WARNING_DAYS = 30;

/** Duración de cada semestre, en meses. Igual que UnitPolicy::SEMESTER_MONTHS. */
export const SEMESTER_MONTHS = 6;

/**
 * «2026-09-23» + 6 meses = «2027-03-23»: mismo día, meses después. Si ese día
 * no existe en el mes destino (31 de agosto → febrero) se queda en el último,
 * igual que addMonthsNoOverflow en el servidor.
 */
export function addMonths(value, months) {
    const [year, month, day] = value.split('-').map(Number);
    const target = new Date(Date.UTC(year, month - 1 + months, 1));
    const lastDay = new Date(Date.UTC(target.getUTCFullYear(), target.getUTCMonth() + 1, 0)).getUTCDate();
    target.setUTCDate(Math.min(day, lastDay));

    return target.toISOString().slice(0, 10);
}

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

/**
 * Qué pagos del periodo vigente se pueden registrar, con el motivo si no hay
 * ninguno. Son las mismas reglas que valida el servidor: sin periodo no hay
 * qué pagar, cada pago se registra una vez y el segundo necesita su fecha
 * límite porque de ahí arranca la renovación.
 *
 * @returns {{ payable: Array<'first'|'second'>, reason: string|null }}
 */
export function payablePayments(unit) {
    if (!unit?.policy_id) return { payable: [], reason: 'La unidad no tiene periodo vigente' };

    const payable = [];

    if (!unit.first_payment?.paid_at) payable.push('first');
    if (!unit.second_payment?.paid_at && unit.second_payment?.ends_on) payable.push('second');

    if (payable.length) return { payable, reason: null };

    return {
        payable,
        reason: unit.second_payment?.paid_at ? 'Los dos pagos ya están registrados' : 'Falta la fecha límite del segundo pago',
    };
}

/** Pesos mexicanos, siempre con centavos: los importes se comparan de un vistazo. */
export function money(amount) {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount ?? 0);
}
