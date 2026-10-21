<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CircleCheck } from 'lucide-vue-next';
import { computed, ref } from 'vue';
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

/* ---------- Plazos ---------- */

/**
 * Lo vencido y luego cuatro semanas desde hoy. La última llega hasta el fin de
 * la ventana de aviso (día 30), así que tiene un par de días más que las otras.
 */
const PERIODS = [
    { key: 'overdue', label: 'Vencido', from: -Infinity, to: -1 },
    { key: 'week1', label: 'Esta semana', from: 0, to: 7 },
    { key: 'week2', label: 'Semana 2', from: 8, to: 14 },
    { key: 'week3', label: 'Semana 3', from: 15, to: 21 },
    { key: 'week4', label: 'Semana 4', from: 22, to: Infinity },
];

const PLURALS = { licenses: ['licencia', 'licencias'], payments: ['pago', 'pagos'], policies: ['póliza', 'pólizas'] };

function dayLabel(days) {
    const date = new Date(today.value);
    date.setDate(date.getDate() + days);

    return shortDate(date);
}

const periods = computed(() => {
    const list = PERIODS.map((period) => {
        const rows = items.value.filter((item) => item.days >= period.from && item.days <= period.to);

        return {
            ...period,
            rows,
            range: period.key === 'overdue' ? 'Antes de hoy' : `${dayLabel(period.from)} al ${dayLabel(Math.min(period.to, props.warning_days))}`,
            modules: visibleModules.value.map((module) => ({ module, count: rows.filter((item) => item.module === module).length })),
        };
    });

    // Las barras se miden contra la columna más cargada: así se comparan entre sí.
    const max = Math.max(1, ...list.map((period) => period.rows.length));

    return list.map((period) => ({
        ...period,
        modules: period.modules.map((entry) => ({ ...entry, width: (entry.count / max) * 100 })),
    }));
});

/** El plazo elegido filtra la lista de abajo; tocarlo otra vez la deja completa. */
const selected = ref(null);

function select(key) {
    selected.value = selected.value === key ? null : key;
}

const markerTone = (item) => (item.days < 0 ? 'bg-red-500 dark:bg-red-400' : 'bg-amber-500 dark:bg-amber-400');

/* ---------- Cola ---------- */

const groups = computed(() => periods.value.filter((period) => period.rows.length && (!selected.value || period.key === selected.value)));

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

            <!-- Plazos: cuánto vence en cada uno; elegir uno filtra la lista de abajo -->
            <section v-if="visibleModules.length" aria-labelledby="periods-title">
                <h2 id="periods-title" class="sr-only">Vencimientos por plazo</h2>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                    <button
                        v-for="period in periods"
                        :key="period.key"
                        type="button"
                        :class="[
                            PANEL,
                            selected === period.key
                                ? 'border-brand ring-2 ring-brand/20 dark:border-white/50 dark:ring-white/15'
                                : 'hover:border-slate-300 dark:hover:border-white/20',
                            period.key === 'overdue' && period.rows.length ? 'border-t-[3px] border-t-red-500 dark:border-t-red-400' : '',
                        ]"
                        class="flex cursor-pointer flex-col gap-3 p-4 text-left transition-[border-color,box-shadow] duration-150 focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-brand/30 disabled:cursor-default disabled:opacity-60 dark:focus-visible:ring-white/30"
                        :disabled="!period.rows.length"
                        :aria-pressed="selected === period.key"
                        @click="select(period.key)"
                    >
                        <span>
                            <span
                                class="block text-sm font-bold"
                                :class="period.key === 'overdue' && period.rows.length ? 'text-red-700 dark:text-red-300' : 'text-slate-800 dark:text-white'"
                            >
                                {{ period.label }}
                            </span>
                            <span class="block text-xs text-slate-400 tabular-nums dark:text-brand-gray/70">{{ period.range }}</span>
                        </span>

                        <span class="text-[2rem] leading-none font-bold text-slate-900 tabular-nums dark:text-white">{{ period.rows.length }}</span>

                        <span class="flex flex-col gap-1.5">
                            <span v-for="entry in period.modules" :key="entry.module" class="flex flex-col gap-1">
                                <span class="flex items-center gap-1.5 text-xs text-slate-600 dark:text-brand-gray">
                                    <span class="size-2 shrink-0 bg-slate-400 dark:bg-brand-gray" :class="MODULES[entry.module].shape" aria-hidden="true" />
                                    <span class="font-semibold tabular-nums">{{ entry.count }}</span>
                                    {{ PLURALS[entry.module][entry.count === 1 ? 0 : 1] }}
                                </span>
                                <span class="h-1 overflow-hidden rounded-full bg-slate-100 dark:bg-white/[0.06]" aria-hidden="true">
                                    <span
                                        class="block h-full rounded-full transition-[width] duration-300"
                                        :class="period.key === 'overdue' ? 'bg-red-500 dark:bg-red-400' : 'bg-brand dark:bg-brand-gray'"
                                        :style="{ width: `${entry.width}%` }"
                                    />
                                </span>
                            </span>
                        </span>
                    </button>
                </div>
            </section>

            <div class="grid grid-cols-1 items-start gap-4 lg:grid-cols-3">
                <!-- Cola: los pendientes de cada plazo, o solo los del plazo elegido -->
                <section v-if="visibleModules.length" :class="PANEL" class="overflow-hidden lg:col-span-2" aria-labelledby="queue-title">
                    <div class="flex items-center justify-between gap-3 border-b border-slate-200/80 px-4 py-3 md:px-5 dark:border-white/[0.08]">
                        <h2 id="queue-title" class="text-sm font-bold text-slate-800 dark:text-white">Por atender</h2>
                        <button
                            v-if="selected"
                            type="button"
                            class="cursor-pointer rounded-md text-xs font-semibold text-brand hover:underline focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-brand/25 dark:text-brand-gray dark:hover:text-white"
                            @click="selected = null"
                        >
                            Ver todos los plazos
                        </button>
                    </div>

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
                                            {{ item.key.startsWith('bond-') ? 'Fianza' : MODULES[item.module].label }}<template v-if="item.subtitle">, {{ item.subtitle }}</template>
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

