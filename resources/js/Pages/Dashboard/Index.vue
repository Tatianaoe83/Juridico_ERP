<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { BarElement, CategoryScale, Chart as ChartJS, LinearScale, Tooltip } from 'chart.js';
import { BellRing, CalendarClock, CircleCheck, FileBadge, FileWarning, LayoutDashboard, ShieldCheck, Truck } from 'lucide-vue-next';
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import AppShell from '@/Layouts/AppShell.vue';
import { useTheme } from '@/composables/useTheme';
import { localDate } from '@/lib/licenses';
import { money } from '@/lib/units';

ChartJS.register(BarElement, CategoryScale, LinearScale, Tooltip);

/**
 * Cada sección es { total, counts: { estado: n }, items: [{ key, title,
 * subtitle, date, status, href, amount?, currency? }] }, o null si el rol no
 * puede ver ese módulo. Todo llega completo. Los documentos además traen
 * module, type y missing: la lista de lo que falta.
 */
const props = defineProps({
    licenses: { type: Object, default: null },
    fleets: { type: Object, default: null },
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


function remaining(days) {
    if (days === 0) return 'hoy';

    return days === 1 ? 'mañana' : `en ${days} días`;
}

/* ---------- Módulos ---------- */

// expiring: el estado con que cada módulo marca lo por vencer.
const MODULES = {
    licenses: { label: 'Licencia', plural: 'Licencias', expiring: 'expiring', href: '/licencias', badge: 'bg-blue-50 text-blue-700 ring-blue-600/15 dark:bg-blue-400/10 dark:text-blue-300 dark:ring-blue-400/20' },
    fleets: { label: 'Flotilla', plural: 'Flotillas', expiring: 'expiring', href: '/flotillas', badge: 'bg-cyan-50 text-cyan-700 ring-cyan-600/15 dark:bg-cyan-400/10 dark:text-cyan-300 dark:ring-cyan-400/20' },
    payments: { label: 'Pago', plural: 'Pagos de flotilla', expiring: 'pending', href: '/flotillas', badge: 'bg-cyan-50 text-cyan-700 ring-cyan-600/15 dark:bg-cyan-400/10 dark:text-cyan-300 dark:ring-cyan-400/20' },
    policies: { label: 'Póliza', plural: 'Pólizas de obra y fianzas', expiring: 'expiring', href: '/polizas', badge: 'bg-amber-50 text-amber-700 ring-amber-600/15 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/20' },
};

const visibleModules = computed(() => Object.keys(MODULES).filter((module) => props[module]));

const count = (section, ...statuses) => statuses.reduce((sum, status) => sum + (section?.counts[status] ?? 0), 0);

/* ---------- Indicadores ---------- */

const figures = computed(() =>
    [
        {
            key: 'alerts',
            label: 'Alertas activas',
            value: props.alerts,
            note: 'Con recordatorio por correo',
            icon: BellRing,
            tone: 'from-brand-light to-brand text-white shadow-brand/25',
            href: null,
        },
        props.licenses && {
            key: 'licenses',
            label: 'Licencias',
            value: props.licenses.total,
            note: `Por vencer en ${props.warning_days} días`,
            icon: FileBadge,
            tone: 'from-blue-500 to-blue-700 text-white shadow-blue-600/25',
            href: '/licencias',
        },
        props.fleets && {
            key: 'fleets',
            label: 'Flotillas',
            value: props.fleets.total,
            note: `Por vencer en ${props.warning_days} días`,
            icon: Truck,
            tone: 'from-cyan-500 to-cyan-700 text-white shadow-cyan-600/25',
            href: '/flotillas',
        },
        props.payments && {
            key: 'payments',
            label: 'Pagos pendientes',
            value: count(props.payments, 'pending'),
            note: `Por vencer en ${props.warning_days} días`,
            icon: Truck,
            tone: 'from-cyan-500 to-cyan-700 text-white shadow-cyan-600/25',
            href: '/flotillas',
        },
        props.policies && {
            key: 'policies',
            label: 'Pólizas de obra y fianzas',
            value: props.policies.total,
            note: `Por vencer en ${props.warning_days} días`,
            icon: ShieldCheck,
            tone: 'from-amber-400 to-amber-600 text-white shadow-amber-500/25',
            href: '/polizas',
        },
        props.documents && {
            key: 'documents',
            label: 'Documentos faltantes',
            value: props.documents.total,
            note: props.documents.total ? 'Sin archivo en el expediente' : 'Expedientes completos',
            icon: FileWarning,
            tone: 'from-slate-400 to-slate-600 text-white shadow-slate-500/25',
            href: null,
        },
    ].filter(Boolean),
);

/* ---------- Pendientes con fecha ---------- */

/** Lo que vence de hoy en adelante, lo más cercano primero. */
const upcoming = computed(() =>
    visibleModules.value
        .flatMap((module) => props[module].items.map((item) => ({ ...item, module, days: daysFrom(item.date) })))
        .filter((item) => item.days >= 0)
        .sort((a, b) => a.days - b.days),
);

/* ---------- Gráfica ---------- */

const chartText = computed(() => (isDark.value ? '#a7a8a9' : '#64748b'));
const chartGrid = computed(() => (isDark.value ? 'rgba(255,255,255,0.06)' : 'rgba(15,23,42,0.06)'));

const barData = computed(() => ({
    labels: visibleModules.value.map((module) => MODULES[module].plural),
    datasets: [
        {
            label: 'Por vencer',
            data: visibleModules.value.map((module) => count(props[module], MODULES[module].expiring)),
            backgroundColor: isDark.value ? '#fbbf24' : '#f59e0b',
            borderRadius: 6,
            borderSkipped: false,
            maxBarThickness: 64,
        },
    ],
}));

const barOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    // Tocar un módulo abre su listado.
    onClick: (_event, elements) => elements.length && router.visit(MODULES[visibleModules.value[elements[0].index]].href),
    onHover: (event, elements) => (event.native.target.style.cursor = elements.length ? 'pointer' : 'default'),
    scales: {
        x: { grid: { display: false }, ticks: { color: chartText.value, font: { weight: 600 } }, border: { display: false } },
        y: { beginAtZero: true, ticks: { color: chartText.value, precision: 0 }, grid: { color: chartGrid.value }, border: { display: false } },
    },
}));

/* ---------- Estilos ---------- */

const CARD =
    'rounded-2xl border border-slate-200/80 bg-white shadow-[0_1px_3px_rgb(2_29_73/0.04),0_8px_24px_-12px_rgb(2_29_73/0.08)] dark:border-white/[0.08] dark:bg-brand-deep dark:shadow-none';

const CARD_HEAD = 'flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 md:px-6 dark:border-white/[0.06]';

const BADGE = 'inline-flex items-center rounded-md px-2.5 py-1 text-xs font-semibold ring-1 ring-inset whitespace-nowrap';

const TH = 'px-5 py-3 text-left text-xs font-bold whitespace-nowrap md:px-6';
const TD = 'px-5 py-3.5 md:px-6';
</script>

<template>
    <Head title="Dashboard" />

    <AppShell :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Encabezado -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span
                        class="grid size-11 place-content-center rounded-xl bg-gradient-to-br from-brand-light to-brand text-white shadow-md shadow-brand/25 ring-1 ring-white/10"
                    >
                        <LayoutDashboard class="size-5" />
                    </span>
                    <h1 class="text-2xl font-bold tracking-tight text-brand dark:text-white">Dashboard</h1>
                </div>
            </div>

            <!-- Indicadores -->
            <!-- Las tarjetas se reparten el ancho: con 2, 3 o 4 según el rol no quedan huecos -->
            <section class="grid grid-cols-[repeat(auto-fit,minmax(16rem,1fr))] gap-4" aria-label="Indicadores">
                <component
                    :is="figure.href ? Link : 'div'"
                    v-for="figure in figures"
                    :key="figure.key"
                    :href="figure.href ?? undefined"
                    :class="[CARD, figure.href && 'transition-[border-color,transform] duration-150 hover:-translate-y-0.5 hover:border-slate-300 dark:hover:border-white/20']"
                    class="flex items-start justify-between gap-3 p-6"
                >
                    <div class="flex min-w-0 flex-col gap-2">
                        <span class="text-sm font-medium text-slate-500 dark:text-brand-gray">{{ figure.label }}</span>
                        <span class="text-4xl leading-none font-bold text-slate-900 tabular-nums dark:text-white">{{ figure.value }}</span>
                        <span class="mt-1 text-sm text-slate-400 dark:text-brand-gray/70">{{ figure.note }}</span>
                    </div>
                    <span class="grid size-12 shrink-0 place-content-center rounded-xl bg-gradient-to-br shadow-md" :class="figure.tone">
                        <component :is="figure.icon" class="size-6" />
                    </span>
                </component>
            </section>

            <div v-if="visibleModules.length" class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Tabla de próximos vencimientos -->
                <section :class="CARD" class="overflow-hidden lg:col-span-2" aria-labelledby="upcoming-title">
                    <div :class="CARD_HEAD">
                        <h2 id="upcoming-title" class="text-base font-bold text-slate-800 dark:text-white">Próximos vencimientos</h2>
                        <span
                            v-if="upcoming.length"
                            class="rounded-md bg-amber-100 px-2 py-0.5 text-sm font-bold text-amber-700 tabular-nums dark:bg-amber-400/15 dark:text-amber-300"
                        >
                            {{ upcoming.length }}
                        </span>
                        <CalendarClock v-else class="size-5 text-slate-400 dark:text-brand-gray/70" />
                    </div>

                    <div v-if="upcoming.length" class="max-h-[22rem] overflow-auto">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0 z-10 bg-slate-50 text-slate-500 dark:bg-brand-deep dark:text-brand-gray">
                                <tr>
                                    <th :class="TH">Vence</th>
                                    <th :class="TH">Concepto</th>
                                    <th :class="[TH, 'hidden sm:table-cell']">Módulo</th>
                                    <th :class="[TH, 'text-right']">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-white/[0.05]">
                                <tr
                                    v-for="item in upcoming"
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
                                        <span class="block truncate text-[0.8rem] text-slate-500 dark:text-brand-gray/80" :title="item.subtitle">
                                            {{ item.subtitle }}<template v-if="item.amount != null"> · {{ money(item.amount, item.currency) }}</template>
                                        </span>
                                    </td>
                                    <td :class="TD" class="hidden sm:table-cell">
                                        <span :class="[BADGE, MODULES[item.module].badge]">
                                            {{ item.key.startsWith('bond-') ? 'Fianza' : MODULES[item.module].label }}
                                        </span>
                                    </td>
                                    <td :class="TD" class="text-right">
                                        <span
                                            :class="[
                                                BADGE,
                                                item.days <= 7
                                                    ? 'bg-amber-50 text-amber-700 ring-amber-600/15 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/20'
                                                    : 'bg-slate-50 text-slate-600 ring-slate-500/15 dark:bg-white/[0.05] dark:text-brand-gray dark:ring-white/10',
                                            ]"
                                        >
                                            Vence {{ remaining(item.days) }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p v-else class="flex items-center gap-2 px-5 py-10 text-sm text-slate-500 md:px-6 dark:text-brand-gray">
                        <CircleCheck class="size-4 text-emerald-600 dark:text-emerald-400" />
                        Nada por vencer en los próximos {{ warning_days }} días.
                    </p>
                </section>

                <!-- Gráfica -->
                <section :class="CARD" aria-labelledby="chart-title">
                    <div :class="CARD_HEAD">
                        <div>
                            <h2 id="chart-title" class="text-base font-bold text-slate-800 dark:text-white">Por vencer por módulo</h2>
                            <p class="text-sm text-slate-400 dark:text-brand-gray/70">Próximos {{ warning_days }} días</p>
                        </div>
                    </div>
                    <div class="h-80 p-5">
                        <Bar :data="barData" :options="barOptions" aria-label="Por vencer por módulo" role="img" />
                    </div>
                </section>
            </div>

            <!-- Documentos: no tienen fecha, por eso no van en la tabla ni en la gráfica -->
            <section v-if="documents" :class="CARD" class="overflow-hidden" aria-labelledby="documents-title">
                <div :class="CARD_HEAD">
                    <h2 id="documents-title" class="text-base font-bold text-slate-800 dark:text-white">Documentos faltantes</h2>
                    <span v-if="documents.total" class="rounded-md bg-slate-100 px-2 py-0.5 text-sm font-bold text-slate-600 tabular-nums dark:bg-white/[0.06] dark:text-brand-gray">
                        {{ documents.total }}
                    </span>
                </div>

                <div v-if="documents.items.length" class="max-h-[22rem] overflow-auto">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 z-10 bg-slate-50 text-slate-500 dark:bg-brand-deep dark:text-brand-gray">
                            <tr>
                                <th :class="TH">Tipo</th>
                                <th :class="TH">Unidad o póliza</th>
                                <th :class="TH">Qué falta</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-white/[0.05]">
                            <tr
                                v-for="item in documents.items"
                                :key="item.key"
                                class="cursor-pointer transition-colors duration-150 hover:bg-slate-50/80 dark:hover:bg-white/[0.025]"
                                @click="router.visit(item.href)"
                            >
                                <td :class="TD" class="whitespace-nowrap">
                                    <span :class="[BADGE, MODULES[item.module].badge]">{{ item.type }}</span>
                                </td>
                                <td :class="TD" class="w-full">
                                    <Link
                                        :href="item.href"
                                        class="font-semibold text-slate-800 hover:underline focus-visible:underline focus-visible:outline-none dark:text-white"
                                        @click.stop
                                    >
                                        {{ item.title }}
                                    </Link>
                                    <span v-if="item.subtitle" class="block text-[0.8rem] text-slate-500 dark:text-brand-gray/80">{{ item.subtitle }}</span>
                                </td>
                                <td :class="TD" class="whitespace-nowrap">
                                    <span class="flex flex-wrap gap-1.5">
                                        <span
                                            v-for="missing in item.missing"
                                            :key="missing"
                                            :class="BADGE"
                                            class="bg-slate-50 text-slate-600 ring-slate-500/15 dark:bg-white/[0.05] dark:text-brand-gray dark:ring-white/10"
                                        >
                                            {{ missing }}
                                        </span>
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p v-else class="flex items-center gap-2 px-5 py-10 text-sm text-slate-500 md:px-6 dark:text-brand-gray">
                    <CircleCheck class="size-4 text-emerald-600 dark:text-emerald-400" />
                    Cada póliza tiene sus archivos.
                </p>
            </section>
        </div>
    </AppShell>
</template>
