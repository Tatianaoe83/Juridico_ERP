<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CircleCheck } from 'lucide-vue-next';
import { computed } from 'vue';
import AppShell from '@/Layouts/AppShell.vue';
import { localDate } from '@/lib/licenses';
import { money } from '@/lib/units';

/**
 * Cada sección es { total, counts: { estado: n }, items: [{ key, title,
 * subtitle, date, status, href, amount?, currency? }] }, o null si el rol no
 * puede ver ese módulo. Licencias, pagos y pólizas llegan completos; los
 * documentos, solo los primeros.
 */
const props = defineProps({
    licenses: { type: Object, default: null },
    payments: { type: Object, default: null },
    policies: { type: Object, default: null },
    documents: { type: Object, default: null },
    alerts: { type: Number, required: true },
    warning_days: { type: Number, required: true },
});

const page = usePage();

const breadcrumbs = [{ label: 'Dashboard' }];

/* ---------- Fechas ---------- */

// El día de hoy del servidor, no del navegador: el mismo corte con que se filtró.
const today = computed(() => localDate(page.props.now.slice(0, 10)));

const daysFrom = (iso) => Math.round((localDate(iso) - today.value) / 86_400_000);

const shortDate = (date) => date.toLocaleDateString('es-MX', { day: 'numeric', month: 'short' }).replace('.', '');

function remaining(days) {
    if (days === 0) return 'hoy';
    if (days < 0) return days === -1 ? 'ayer' : `hace ${-days} días`;

    return days === 1 ? 'mañana' : `en ${days} días`;
}

/* ---------- Módulos ---------- */

/**
 * Cada módulo tiene su forma en la franja: el color dice el estado y la forma
 * el módulo, así nada depende solo del color.
 */
const MODULES = {
    licenses: { label: 'Licencia', shape: 'rounded-full', href: '/licencias' },
    payments: { label: 'Pago', shape: 'rounded-[2px]', href: '/flotillas' },
    policies: { label: 'Póliza', shape: 'rotate-45 rounded-[1px] scale-[0.85]', href: '/polizas' },
};

const visibleModules = computed(() => Object.keys(MODULES).filter((module) => props[module]));

const count = (section, ...statuses) => statuses.reduce((sum, status) => sum + (section?.counts[status] ?? 0), 0);

/* ---------- Cifras ---------- */

const overdueTotal = computed(() => count(props.licenses, 'expired') + count(props.payments, 'overdue') + count(props.policies, 'expired'));

const figures = computed(() =>
    [
        {
            key: 'alerts',
            label: 'Alertas activas',
            value: props.alerts,
            note: overdueTotal.value ? `${overdueTotal.value} ya vencidas` : 'Ninguna vencida',
            urgent: overdueTotal.value > 0,
            href: null,
        },
        props.licenses && {
            key: 'licenses',
            label: 'Permisos por vencer',
            value: count(props.licenses, 'expiring'),
            note: overdue(count(props.licenses, 'expired'), 'vencido', 'vencidos'),
            urgent: count(props.licenses, 'expired') > 0,
            href: '/licencias',
        },
        props.payments && {
            key: 'payments',
            label: 'Flotillas con pago pendiente',
            value: count(props.payments, 'pending'),
            note: overdue(count(props.payments, 'overdue'), 'atrasado', 'atrasados'),
            urgent: count(props.payments, 'overdue') > 0,
            href: '/flotillas',
        },
        props.policies && {
            key: 'policies',
            label: 'Pólizas por vencer',
            value: count(props.policies, 'expiring'),
            note: overdue(count(props.policies, 'expired'), 'vencida', 'vencidas'),
            urgent: count(props.policies, 'expired') > 0,
            href: '/polizas',
        },
        props.documents && {
            key: 'documents',
            label: 'Documentos faltantes',
            value: props.documents.total,
            note: props.documents.total ? 'Sin archivo en el expediente' : 'Expedientes completos',
            urgent: false,
            href: null,
        },
    ].filter(Boolean),
);

function overdue(n, one, many) {
    return n ? `Más ${n} ${n === 1 ? one : many}` : 'Nada vencido';
}

/* ---------- Pendientes con fecha ---------- */

const items = computed(() =>
    visibleModules.value
        .flatMap((module) => props[module].items.map((item) => ({ ...item, module, days: daysFrom(item.date) })))
        .sort((a, b) => a.days - b.days),
);

/* ---------- Franja de vencimientos ---------- */

// Lo vencido ocupa una zona fija a la izquierda: sin importar hace cuánto, ya hay que atenderlo.
const OVERDUE_ZONE = 12;

// Cuántos puntos caben apilados en un día antes de resumir con «+n».
const STACK = 4;

const ticks = computed(() =>
    [0, 7, 14, 21, props.warning_days].map((days) => {
        const date = new Date(today.value);
        date.setDate(date.getDate() + days);

        return { days, left: position(days), label: days === 0 ? 'Hoy' : shortDate(date) };
    }),
);

function position(days) {
    return OVERDUE_ZONE + (Math.min(days, props.warning_days) / props.warning_days) * (100 - OVERDUE_ZONE);
}

const overdueItems = computed(() => items.value.filter((item) => item.days < 0));

/** Un grupo por día con algo pendiente, de hoy al fin de la ventana. */
const days = computed(() => {
    const byDay = new Map();

    items.value.filter((item) => item.days >= 0).forEach((item) => byDay.set(item.days, [...(byDay.get(item.days) ?? []), item]));

    return [...byDay].map(([days, list]) => ({ days, left: position(days), shown: list.slice(0, STACK), extra: list.length - STACK }));
});

const markerTone = (item) => (item.days < 0 ? 'bg-red-500 dark:bg-red-400' : 'bg-amber-500 dark:bg-amber-400');

const markerLabel = (item) => `${MODULES[item.module].label}: ${item.title}, ${item.days < 0 ? 'venció' : 'vence'} ${remaining(item.days)}`;

/* ---------- Cola ---------- */

const groups = computed(() =>
    [
        { key: 'overdue', label: 'Vencido', rows: items.value.filter((item) => item.days < 0) },
        { key: 'week', label: 'Esta semana', rows: items.value.filter((item) => item.days >= 0 && item.days <= 7) },
        { key: 'later', label: `Hasta ${props.warning_days} días`, rows: items.value.filter((item) => item.days > 7) },
    ].filter((group) => group.rows.length),
);

const PANEL = 'rounded-xl border border-slate-200/80 bg-white dark:border-white/[0.08] dark:bg-brand-deep';

const ROW_LINK =
    'transition-colors duration-150 hover:bg-slate-50 focus-visible:bg-brand/[0.05] focus-visible:outline-none dark:hover:bg-white/[0.04] dark:focus-visible:bg-white/[0.07]';
</script>

<template>
    <Head title="Dashboard" />

    <AppShell :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex max-w-7xl flex-col gap-4">
            <h1 class="text-xl font-bold tracking-tight text-brand dark:text-white">Dashboard</h1>

            <!-- Cifras: un solo panel, una celda por indicador -->
            <section
                :class="PANEL"
                class="flex flex-wrap gap-px overflow-hidden bg-slate-200/80 dark:bg-white/[0.08]"
                aria-label="Indicadores"
            >
                <!-- Las celdas crecen hasta llenar su fila: con 3, 4 o 5 indicadores no quedan huecos -->
                <component
                    :is="figure.href ? Link : 'div'"
                    v-for="figure in figures"
                    :key="figure.key"
                    :href="figure.href ?? undefined"
                    class="flex min-w-[10rem] flex-1 flex-col gap-1 bg-white px-4 py-3.5 dark:bg-brand-deep"
                    :class="figure.href && ROW_LINK"
                >
                    <span class="text-[0.78rem] font-medium text-slate-500 dark:text-brand-gray">{{ figure.label }}</span>
                    <span
                        class="text-[2rem] leading-none font-bold tabular-nums"
                        :class="figure.key === 'alerts' && figure.value ? 'text-brand dark:text-white' : 'text-slate-900 dark:text-white'"
                    >
                        {{ figure.value }}
                    </span>
                    <span
                        class="text-xs"
                        :class="figure.urgent ? 'font-semibold text-red-600 dark:text-red-400' : 'text-slate-400 dark:text-brand-gray/70'"
                    >
                        {{ figure.note }}
                    </span>
                </component>
            </section>

            <!-- Franja: cada pendiente en su día, lo vencido a la izquierda de hoy -->
            <section v-if="visibleModules.length" :class="PANEL" class="px-4 pt-4 pb-3 md:px-5" aria-labelledby="runway-title">
                <div class="mb-4 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-2">
                    <h2 id="runway-title" class="text-sm font-bold text-slate-800 dark:text-white">Próximos {{ warning_days }} días</h2>
                    <ul class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500 dark:text-brand-gray">
                        <li v-for="module in visibleModules" :key="module" class="flex items-center gap-1.5">
                            <span class="size-2.5 bg-slate-400 dark:bg-brand-gray" :class="MODULES[module].shape" aria-hidden="true" />
                            {{ MODULES[module].label }}
                        </li>
                    </ul>
                </div>

                <div class="relative h-28">
                    <!-- Zona de vencidos: rayada para que se distinga sin depender del rojo -->
                    <div
                        class="overdue-zone absolute inset-y-0 left-0 flex flex-wrap content-end gap-1 rounded-l-md p-1.5"
                        :style="{ width: `${OVERDUE_ZONE}%` }"
                    >
                        <Link
                            v-for="item in overdueItems.slice(0, 12)"
                            :key="item.key"
                            :href="item.href"
                            class="block size-2.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-1 dark:focus-visible:ring-white"
                            :class="[MODULES[item.module].shape, markerTone(item)]"
                            :title="markerLabel(item)"
                            :aria-label="markerLabel(item)"
                        />
                        <span v-if="overdueItems.length > 12" class="text-[0.65rem] font-semibold text-red-700 dark:text-red-300">
                            +{{ overdueItems.length - 12 }}
                        </span>
                    </div>

                    <!-- Rejilla: una línea por semana, la de hoy marcada -->
                    <span
                        v-for="tick in ticks"
                        :key="tick.days"
                        class="absolute inset-y-0 w-px"
                        :class="tick.days === 0 ? 'bg-brand dark:bg-white/70' : 'bg-slate-200 dark:bg-white/[0.08]'"
                        :style="{ left: `${tick.left}%` }"
                        aria-hidden="true"
                    />

                    <!-- Una columna por día con pendientes, apilada desde abajo -->
                    <div
                        v-for="day in days"
                        :key="day.days"
                        class="absolute bottom-1.5 flex -translate-x-1/2 flex-col-reverse items-center gap-1"
                        :style="{ left: `${day.left}%` }"
                    >
                        <Link
                            v-for="item in day.shown"
                            :key="item.key"
                            :href="item.href"
                            class="block size-2.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-1 dark:focus-visible:ring-white"
                            :class="[MODULES[item.module].shape, markerTone(item)]"
                            :title="markerLabel(item)"
                            :aria-label="markerLabel(item)"
                        />
                        <span v-if="day.extra > 0" class="text-[0.65rem] font-semibold text-slate-500 dark:text-brand-gray">+{{ day.extra }}</span>
                    </div>
                </div>

                <!-- Escala -->
                <div class="relative mt-1.5 h-4 text-[0.68rem] text-slate-400 tabular-nums dark:text-brand-gray/70" aria-hidden="true">
                    <span class="absolute left-0 font-semibold text-red-600 dark:text-red-400">Vencido</span>
                    <span
                        v-for="tick in ticks"
                        :key="tick.days"
                        class="absolute whitespace-nowrap"
                        :class="[tick.days === 0 ? 'font-semibold text-brand dark:text-white' : '', tick.days === warning_days ? '-translate-x-full' : '-translate-x-1/2', tick.days === 7 || tick.days === 21 ? 'max-sm:hidden' : '']"
                        :style="{ left: `${tick.left}%` }"
                    >
                        {{ tick.label }}
                    </span>
                </div>
            </section>

            <div class="grid grid-cols-1 items-start gap-4 lg:grid-cols-3">
                <!-- Cola: lo mismo que la franja, en lista y por plazo -->
                <section v-if="visibleModules.length" :class="PANEL" class="overflow-hidden lg:col-span-2" aria-labelledby="queue-title">
                    <h2 id="queue-title" class="border-b border-slate-200/80 px-4 py-3 text-sm font-bold text-slate-800 md:px-5 dark:border-white/[0.08] dark:text-white">
                        Por atender
                    </h2>

                    <div v-for="group in groups" :key="group.key">
                        <h3
                            class="flex items-baseline gap-2 bg-slate-50 px-4 py-1.5 text-xs font-semibold md:px-5 dark:bg-white/[0.03]"
                            :class="group.key === 'overdue' ? 'text-red-700 dark:text-red-300' : 'text-slate-600 dark:text-brand-gray'"
                        >
                            {{ group.label }}
                            <span class="font-normal text-slate-400 tabular-nums dark:text-brand-gray/70">{{ group.rows.length }}</span>
                        </h3>

                        <ul class="divide-y divide-slate-100 dark:divide-white/[0.05]">
                            <li v-for="item in group.rows" :key="item.key">
                                <Link :href="item.href" class="grid grid-cols-[3.5rem_1fr_auto] items-center gap-x-3 px-4 py-2.5 md:px-5" :class="ROW_LINK">
                                    <span class="text-xs font-semibold text-slate-700 tabular-nums dark:text-slate-200">{{ shortDate(localDate(item.date)) }}</span>

                                    <span class="min-w-0">
                                        <span class="flex items-center gap-2">
                                            <span class="size-2 shrink-0" :class="[MODULES[item.module].shape, markerTone(item)]" aria-hidden="true" />
                                            <span class="truncate text-sm font-semibold text-slate-800 dark:text-white" :title="item.title">{{ item.title }}</span>
                                        </span>
                                        <span class="block truncate pl-4 text-xs text-slate-500 dark:text-brand-gray/80" :title="item.subtitle">
                                            {{ MODULES[item.module].label }}<template v-if="item.subtitle">, {{ item.subtitle }}</template>
                                        </span>
                                    </span>

                                    <span class="text-right">
                                        <span v-if="item.amount != null" class="block text-xs font-semibold text-slate-700 tabular-nums dark:text-slate-200">
                                            {{ money(item.amount, item.currency) }}
                                        </span>
                                        <span
                                            class="block text-xs whitespace-nowrap"
                                            :class="item.days < 0 ? 'font-semibold text-red-600 dark:text-red-400' : 'text-slate-500 dark:text-brand-gray/80'"
                                        >
                                            {{ item.days < 0 ? 'Venció' : 'Vence' }} {{ remaining(item.days) }}
                                        </span>
                                    </span>
                                </Link>
                            </li>
                        </ul>
                    </div>

                    <p v-if="!groups.length" class="flex items-center gap-2 px-4 py-8 text-sm text-slate-500 md:px-5 dark:text-brand-gray">
                        <CircleCheck class="size-4 text-emerald-600 dark:text-emerald-400" />
                        Nada vence ni está atrasado en los próximos {{ warning_days }} días.
                    </p>
                </section>

                <!-- Documentos: no tienen fecha, por eso no van en la franja -->
                <section v-if="documents" :class="PANEL" class="overflow-hidden" aria-labelledby="documents-title">
                    <h2 id="documents-title" class="border-b border-slate-200/80 px-4 py-3 text-sm font-bold text-slate-800 dark:border-white/[0.08] dark:text-white">
                        Documentos faltantes
                    </h2>

                    <ul v-if="documents.items.length" class="divide-y divide-slate-100 dark:divide-white/[0.05]">
                        <li v-for="item in documents.items" :key="item.key">
                            <Link :href="item.href" class="block px-4 py-2.5" :class="ROW_LINK">
                                <span class="block truncate text-sm font-semibold text-slate-800 dark:text-white" :title="item.title">{{ item.title }}</span>
                                <span class="block text-xs text-slate-500 dark:text-brand-gray/80">{{ item.subtitle }}</span>
                            </Link>
                        </li>
                    </ul>

                    <p v-else class="flex items-center gap-2 px-4 py-8 text-sm text-slate-500 dark:text-brand-gray">
                        <CircleCheck class="size-4 text-emerald-600 dark:text-emerald-400" />
                        Cada póliza tiene sus archivos.
                    </p>

                    <p
                        v-if="documents.total > documents.items.length"
                        class="border-t border-slate-200/80 px-4 py-2 text-xs text-slate-400 dark:border-white/[0.08] dark:text-brand-gray/70"
                    >
                        {{ documents.total - documents.items.length }} más sin mostrar.
                    </p>
                </section>
            </div>
        </div>
    </AppShell>
</template>

<style scoped>
.overdue-zone {
    background: repeating-linear-gradient(-45deg, rgb(239 68 68 / 0.1) 0 4px, transparent 4px 8px);
}
</style>
