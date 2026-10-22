<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { BarElement, CategoryScale, Chart as ChartJS, Legend, LinearScale, Tooltip } from 'chart.js';
import { BellRing, CalendarClock, CircleCheck, FileBadge, FileWarning, LayoutDashboard, ShieldCheck, Truck } from 'lucide-vue-next';
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import AppShell from '@/Layouts/AppShell.vue';
import { useTheme } from '@/composables/useTheme';
import { localDate } from '@/lib/licenses';
import { money } from '@/lib/units';

ChartJS.register(BarElement, CategoryScale, LinearScale, Tooltip, Legend);

/**
 * Cada sección es { total, counts: { estado: n }, items: [{ key, title,
 * subtitle, date, status, href, amount?, currency? }] }, o null si el rol no
 * puede ver ese módulo. Todo llega completo.
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
const { isDark } = useTheme();

const breadcrumbs = [{ label: 'Dashboard' }];

/* ---------- Fechas ---------- */

// El día de hoy del servidor, no del navegador: el mismo corte con que se filtró.
const today = computed(() => localDate(page.props.now.slice(0, 10)));

const daysFrom = (iso) => Math.round((localDate(iso) - today.value) / 86_400_000);

const shortDate = (date) => date.toLocaleDateString('es-MX', { day: 'numeric', month: 'short' }).replace('.', '');

const longToday = computed(() => today.value.toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }));

function remaining(days) {
    if (days === 0) return 'hoy';
    if (days < 0) return days === -1 ? 'ayer' : `hace ${-days} días`;

    return days === 1 ? 'mañana' : `en ${days} días`;
}

/* ---------- Módulos ---------- */

// overdue y expiring: el estado con que cada módulo marca lo vencido y lo por vencer.
const MODULES = {
    licenses: { label: 'Licencia', plural: 'Licencias', overdue: 'expired', expiring: 'expiring', href: '/licencias', badge: 'bg-blue-50 text-blue-700 ring-blue-600/15 dark:bg-blue-400/10 dark:text-blue-300 dark:ring-blue-400/20' },
    payments: { label: 'Pago', plural: 'Pagos de flotilla', overdue: 'overdue', expiring: 'pending', href: '/flotillas', badge: 'bg-cyan-50 text-cyan-700 ring-cyan-600/15 dark:bg-cyan-400/10 dark:text-cyan-300 dark:ring-cyan-400/20' },
    policies: { label: 'Póliza', plural: 'Pólizas de obra y fianzas', overdue: 'expired', expiring: 'expiring', href: '/polizas', badge: 'bg-amber-50 text-amber-700 ring-amber-600/15 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/20' },
};

const visibleModules = computed(() => Object.keys(MODULES).filter((module) => props[module]));

const count = (section, ...statuses) => statuses.reduce((sum, status) => sum + (section?.counts[status] ?? 0), 0);

/* ---------- Indicadores ---------- */

function overdue(n, one, many) {
    return n ? `${n} ${n === 1 ? one : many}` : 'Nada vencido';
}

/** El número grande es el total; abajo, cuánto ya venció y cuánto está por vencer. */
function breakdown(section) {
    const expired = count(section, 'expired');
    const expiring = count(section, 'expiring');

    return `${expired} ${expired === 1 ? 'vencida' : 'vencidas'} · ${expiring} por vencer`;
}

const figures = computed(() =>
    [
        {
            key: 'alerts',
            label: 'Alertas activas',
            value: props.alerts,
            note: 'Con recordatorio por correo',
            urgent: false,
            icon: BellRing,
            tone: 'from-brand-light to-brand text-white shadow-brand/25',
            href: null,
        },
        props.licenses && {
            key: 'licenses',
            label: 'Licencias',
            value: props.licenses.total,
            note: breakdown(props.licenses),
            urgent: count(props.licenses, 'expired') > 0,
            icon: FileBadge,
            tone: 'from-blue-500 to-blue-700 text-white shadow-blue-600/25',
            href: '/licencias',
        },
        props.payments && {
            key: 'payments',
            label: 'Pagos pendientes',
            value: count(props.payments, 'pending'),
            note: overdue(count(props.payments, 'overdue'), 'atrasado', 'atrasados'),
            urgent: count(props.payments, 'overdue') > 0,
            icon: Truck,
            tone: 'from-cyan-500 to-cyan-700 text-white shadow-cyan-600/25',
            href: '/flotillas',
        },
        props.policies && {
            key: 'policies',
            label: 'Pólizas de obra y fianzas',
            value: props.policies.total,
            note: breakdown(props.policies),
            urgent: count(props.policies, 'expired') > 0,
            icon: ShieldCheck,
            tone: 'from-amber-400 to-amber-600 text-white shadow-amber-500/25',
            href: '/polizas',
        },
        props.documents && {
            key: 'documents',
            label: 'Documentos faltantes',
            value: props.documents.total,
            note: props.documents.total ? 'Sin archivo en el expediente' : 'Expedientes completos',
            urgent: false,
            icon: FileWarning,
            tone: 'from-slate-400 to-slate-600 text-white shadow-slate-500/25',
            href: null,
        },
    ].filter(Boolean),
);

/* ---------- Pendientes con fecha ---------- */

const items = computed(() =>
    visibleModules.value
        .flatMap((module) => props[module].items.map((item) => ({ ...item, module, days: daysFrom(item.date) })))
        .sort((a, b) => a.days - b.days),
);

/** Por atender: solo lo ya vencido; lo que viene va en próximos vencimientos. */
const overdueRows = computed(() => items.value.filter((item) => item.days < 0));

/** Lo que vence primero de hoy en adelante. */
const upcoming = computed(() => items.value.filter((item) => item.days >= 0).slice(0, 5));

/* ---------- Gráfica ---------- */

const chartText = computed(() => (isDark.value ? '#a7a8a9' : '#64748b'));
const chartGrid = computed(() => (isDark.value ? 'rgba(255,255,255,0.06)' : 'rgba(15,23,42,0.06)'));

/** [claro, oscuro] */
const STATUS_COLORS = { overdue: ['#dc2626', '#f87171'], expiring: ['#f59e0b', '#fbbf24'] };

const barData = computed(() => ({
    labels: visibleModules.value.map((module) => MODULES[module].plural),
    datasets: [
        { key: 'overdue', label: 'Vencido' },
        { key: 'expiring', label: 'Por vencer' },
    ].map(({ key, label }) => ({
        label,
        data: visibleModules.value.map((module) => count(props[module], MODULES[module][key])),
        backgroundColor: STATUS_COLORS[key][isDark.value ? 1 : 0],
        borderRadius: 4,
        borderSkipped: false,
        maxBarThickness: 28,
    })),
}));

const barOptions = computed(() => ({
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    // Tocar un módulo abre su listado.
    onClick: (_event, elements) => elements.length && router.visit(MODULES[visibleModules.value[elements[0].index]].href),
    onHover: (event, elements) => (event.native.target.style.cursor = elements.length ? 'pointer' : 'default'),
    plugins: {
        legend: { position: 'bottom', labels: { color: chartText.value, usePointStyle: true, pointStyle: 'rectRounded', boxWidth: 8, padding: 16 } },
    },
    scales: {
        x: { stacked: true, beginAtZero: true, ticks: { color: chartText.value, precision: 0 }, grid: { color: chartGrid.value }, border: { display: false } },
        y: { stacked: true, grid: { display: false }, ticks: { color: chartText.value, font: { weight: 600 } }, border: { display: false } },
    },
}));

/* ---------- Estilos ---------- */

const CARD =
    'rounded-2xl border border-slate-200/80 bg-white shadow-[0_1px_3px_rgb(2_29_73/0.04),0_8px_24px_-12px_rgb(2_29_73/0.08)] dark:border-white/[0.08] dark:bg-brand-deep dark:shadow-none';

const CARD_HEAD = 'flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 md:px-5 dark:border-white/[0.06]';

const BADGE = 'inline-flex items-center rounded-md px-2 py-0.5 text-[0.68rem] font-semibold ring-1 ring-inset whitespace-nowrap';

const TH = 'px-4 py-2 text-left text-[0.68rem] font-bold whitespace-nowrap md:px-5';
const TD = 'px-4 py-2.5 md:px-5';
</script>

<template>
    <Head title="Dashboard" />

    <AppShell :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex max-w-7xl flex-col gap-4">
            <!-- Encabezado -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span
                        class="grid size-9 place-content-center rounded-xl bg-gradient-to-br from-brand-light to-brand text-white shadow-md shadow-brand/25 ring-1 ring-white/10"
                    >
                        <LayoutDashboard class="size-4" />
                    </span>
                    <div>
                        <h1 class="text-xl font-bold tracking-tight text-brand dark:text-white">Dashboard</h1>
                        <p class="text-xs text-slate-500 first-letter:uppercase dark:text-brand-gray">{{ longToday }}</p>
                    </div>
                </div>
                <p class="text-xs text-slate-500 dark:text-brand-gray">
                    Ventana de aviso: <span class="font-bold text-slate-800 dark:text-white">{{ warning_days }} días</span>
                </p>
            </div>

            <!-- Indicadores -->
            <!-- Las tarjetas se reparten el ancho: con 2, 3 o 4 según el rol no quedan huecos -->
            <section class="grid grid-cols-[repeat(auto-fit,minmax(14rem,1fr))] gap-3" aria-label="Indicadores">
                <component
                    :is="figure.href ? Link : 'div'"
                    v-for="figure in figures"
                    :key="figure.key"
                    :href="figure.href ?? undefined"
                    :class="[CARD, figure.href && 'transition-[border-color,transform] duration-150 hover:-translate-y-0.5 hover:border-slate-300 dark:hover:border-white/20']"
                    class="flex items-start justify-between gap-3 p-4"
                >
                    <div class="flex min-w-0 flex-col gap-1">
                        <span class="text-[0.78rem] font-medium text-slate-500 dark:text-brand-gray">{{ figure.label }}</span>
                        <span class="text-3xl leading-none font-bold text-slate-900 tabular-nums dark:text-white">{{ figure.value }}</span>
                        <span
                            class="mt-1 text-xs"
                            :class="figure.urgent ? 'font-semibold text-red-600 dark:text-red-400' : 'text-slate-400 dark:text-brand-gray/70'"
                        >
                            {{ figure.note }}
                        </span>
                    </div>
                    <span class="grid size-10 shrink-0 place-content-center rounded-xl bg-gradient-to-br shadow-md" :class="figure.tone">
                        <component :is="figure.icon" class="size-5" />
                    </span>
                </component>
            </section>

            <!-- Gráficas -->
            <div v-if="visibleModules.length" class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <section :class="CARD" class="lg:col-span-2" aria-labelledby="chart-title">
                    <div :class="CARD_HEAD">
                        <div>
                            <h2 id="chart-title" class="text-sm font-bold text-slate-800 dark:text-white">Estado por módulo</h2>
                            <p class="text-xs text-slate-400 dark:text-brand-gray/70">Vencido y por vencer en los próximos {{ warning_days }} días</p>
                        </div>
                    </div>
                    <div class="flex flex-col gap-3 p-4">
                        <div class="h-52">
                            <Bar :data="barData" :options="barOptions" aria-label="Vencido y por vencer por módulo" role="img" />
                        </div>
                        <!-- La gráfica da la proporción; esto, el número exacto -->
                        <ul class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                            <li v-for="module in visibleModules" :key="module">
                                <Link
                                    :href="MODULES[module].href"
                                    class="flex items-center justify-between gap-2 rounded-lg border border-slate-100 px-3 py-2 text-xs transition-colors duration-150 hover:border-slate-300 dark:border-white/[0.06] dark:hover:border-white/20"
                                >
                                    <span class="font-semibold text-slate-700 dark:text-slate-200">{{ MODULES[module].plural }}</span>
                                    <span class="flex gap-1.5 tabular-nums">
                                        <span class="font-bold text-red-600 dark:text-red-400" title="Vencido">{{ count(props[module], MODULES[module].overdue) }}</span>
                                        <span class="text-slate-300 dark:text-white/20">/</span>
                                        <span class="font-bold text-amber-600 dark:text-amber-400" title="Por vencer">{{ count(props[module], MODULES[module].expiring) }}</span>
                                    </span>
                                </Link>
                            </li>
                        </ul>
                    </div>
                </section>

                <section :class="CARD" class="overflow-hidden" aria-labelledby="upcoming-title">
                    <div :class="CARD_HEAD">
                        <h2 id="upcoming-title" class="text-sm font-bold text-slate-800 dark:text-white">Próximos vencimientos</h2>
                        <CalendarClock class="size-4 text-slate-400 dark:text-brand-gray/70" />
                    </div>

                    <ul v-if="upcoming.length" class="divide-y divide-slate-100 dark:divide-white/[0.05]">
                        <li v-for="item in upcoming" :key="item.key">
                            <Link
                                :href="item.href"
                                class="flex items-center gap-3 px-4 py-2.5 transition-colors duration-150 hover:bg-slate-50/80 focus-visible:bg-brand/[0.05] focus-visible:outline-none md:px-5 dark:hover:bg-white/[0.025]"
                            >
                                <span
                                    class="flex w-11 shrink-0 flex-col items-center rounded-lg py-1 leading-tight"
                                    :class="item.days <= 7 ? 'bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300' : 'bg-slate-50 text-slate-600 dark:bg-white/[0.05] dark:text-brand-gray'"
                                >
                                    <span class="text-sm font-bold tabular-nums">{{ localDate(item.date).getDate() }}</span>
                                    <span class="text-[0.6rem] font-semibold uppercase">{{ shortDate(localDate(item.date)).split(' ')[1] }}</span>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold text-slate-800 dark:text-white" :title="item.title">{{ item.title }}</span>
                                    <span class="block truncate text-xs text-slate-500 dark:text-brand-gray/80">
                                        {{ item.key.startsWith('bond-') ? 'Fianza' : MODULES[item.module].label }} · vence {{ remaining(item.days) }}
                                    </span>
                                </span>
                            </Link>
                        </li>
                    </ul>

                    <p v-else class="flex items-center gap-2 px-4 py-10 text-sm text-slate-500 md:px-5 dark:text-brand-gray">
                        <CircleCheck class="size-4 text-emerald-600 dark:text-emerald-400" />
                        Nada por vencer en los próximos {{ warning_days }} días.
                    </p>
                </section>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:auto-rows-[minmax(18rem,auto)] lg:grid-cols-3">
                <!-- Tabla de pendientes -->
                <section v-if="visibleModules.length" :class="CARD" class="overflow-hidden lg:col-span-2" aria-labelledby="queue-title">
                    <div :class="CARD_HEAD">
                        <h2 id="queue-title" class="text-sm font-bold text-slate-800 dark:text-white">Vencidos</h2>
                        <span
                            v-if="overdueRows.length"
                            class="rounded-md bg-red-100 px-2 py-0.5 text-xs font-bold text-red-700 tabular-nums dark:bg-red-400/15 dark:text-red-300"
                        >
                            {{ overdueRows.length }}
                        </span>
                    </div>

                    <div v-if="overdueRows.length" class="max-h-[28rem] overflow-auto">
                        <table class="w-full text-[0.8rem]">
                            <thead class="sticky top-0 z-10 bg-slate-50 text-slate-500 dark:bg-brand-deep dark:text-brand-gray">
                                <tr>
                                    <th :class="TH">Venció</th>
                                    <th :class="TH">Concepto</th>
                                    <th :class="[TH, 'hidden sm:table-cell']">Módulo</th>
                                    <th :class="[TH, 'text-right']">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-white/[0.05]">
                                <tr
                                    v-for="item in overdueRows"
                                    :key="item.key"
                                    class="cursor-pointer transition-colors duration-150 hover:bg-slate-50/80 dark:hover:bg-white/[0.025]"
                                    @click="router.visit(item.href)"
                                >
                                    <td :class="TD" class="font-semibold whitespace-nowrap text-slate-700 tabular-nums dark:text-slate-200">
                                        {{ shortDate(localDate(item.date)) }}
                                    </td>
                                    <td :class="TD" class="w-full max-w-0">
                                        <Link
                                            :href="item.href"
                                            class="block truncate font-semibold text-slate-800 hover:underline focus-visible:underline focus-visible:outline-none dark:text-white"
                                            :title="item.title"
                                            @click.stop
                                        >
                                            {{ item.title }}
                                        </Link>
                                        <span class="block truncate text-xs text-slate-500 dark:text-brand-gray/80" :title="item.subtitle">
                                            {{ item.subtitle }}<template v-if="item.amount != null"> · {{ money(item.amount, item.currency) }}</template>
                                        </span>
                                    </td>
                                    <td :class="TD" class="hidden sm:table-cell">
                                        <span :class="[BADGE, MODULES[item.module].badge]">
                                            {{ item.key.startsWith('bond-') ? 'Fianza' : MODULES[item.module].label }}
                                        </span>
                                    </td>
                                    <td :class="TD" class="text-right">
                                        <span :class="BADGE" class="bg-red-50 text-red-700 ring-red-600/15 dark:bg-red-400/10 dark:text-red-300 dark:ring-red-400/20">
                                            Venció {{ remaining(item.days) }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p v-else class="flex items-center gap-2 px-4 py-10 text-sm text-slate-500 md:px-5 dark:text-brand-gray">
                        <CircleCheck class="size-4 text-emerald-600 dark:text-emerald-400" />
                        Nada vencido.
                    </p>
                </section>

                <!-- Documentos: no tienen fecha, por eso no van en la gráfica. En computadora
                     va absoluto: no estira la fila, toma el alto de Vencidos y desplaza lo que sobre. -->
                <div v-if="documents" class="relative">
                <section :class="CARD" class="flex flex-col overflow-hidden lg:absolute lg:inset-0" aria-labelledby="documents-title">
                    <div :class="CARD_HEAD" class="shrink-0">
                        <h2 id="documents-title" class="text-sm font-bold text-slate-800 dark:text-white">Documentos faltantes</h2>
                        <span v-if="documents.total" class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-600 tabular-nums dark:bg-white/[0.06] dark:text-brand-gray">
                            {{ documents.total }}
                        </span>
                    </div>

                    <ul v-if="documents.items.length" class="max-h-[28rem] divide-y divide-slate-100 overflow-y-auto lg:max-h-none lg:min-h-0 lg:flex-1 dark:divide-white/[0.05]">
                        <li v-for="item in documents.items" :key="item.key">
                            <Link
                                :href="item.href"
                                class="flex items-start gap-3 px-4 py-2.5 transition-colors duration-150 hover:bg-slate-50/80 focus-visible:bg-brand/[0.05] focus-visible:outline-none md:px-5 dark:hover:bg-white/[0.025]"
                            >
                                <FileWarning class="mt-0.5 size-4 shrink-0 text-slate-400 dark:text-brand-gray/70" />
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-semibold text-slate-800 dark:text-white" :title="item.title">{{ item.title }}</span>
                                    <span class="block text-xs text-slate-500 dark:text-brand-gray/80">{{ item.subtitle }}</span>
                                </span>
                            </Link>
                        </li>
                    </ul>

                    <p v-else class="flex items-center gap-2 px-4 py-10 text-sm text-slate-500 md:px-5 dark:text-brand-gray">
                        <CircleCheck class="size-4 text-emerald-600 dark:text-emerald-400" />
                        Cada póliza tiene sus archivos.
                    </p>
                </section>
                </div>
            </div>
        </div>
    </AppShell>
</template>
