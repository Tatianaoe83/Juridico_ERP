<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Clock, Eye, FileCheck, Layers, Pencil, Search, ShieldAlert, ShieldCheck, Trash2, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppShell from '@/Layouts/AppShell.vue';
import FilterSelect from '@/components/app/FilterSelect.vue';
import { usePermissions } from '@/composables/usePermissions';
import { COVERAGE_STATUS, COVERAGE_TYPES, coverageStatus } from '@/lib/coverages';
import { money, shortDate } from '@/lib/units';

const props = defineProps({
    /** { data: [{ key, type, number, provider, subject, detail, valid_from, valid_until, amount, status, payments }], meta } */
    coverages: { type: Object, required: true },
    /** { active, expiring, expired, warning_days } — de todo, sin filtros. */
    stats: { type: Object, required: true },
    /** { search, type } — lo que ya viene aplicado. */
    filters: { type: Object, default: () => ({}) },
});

const breadcrumbs = [{ label: 'Inicio', href: '/calendario' }, { label: 'Pólizas y Fianzas' }];

// La unidad de una póliza solo lleva a su ficha si puede ver flotillas.
const { can } = usePermissions();

/* ---------- Búsqueda, tipo y páginas ---------- */

// El servidor pagina de 10 en 10, igual que las demás tablas.
const PER_PAGE = 10;

const search = ref(props.filters.search ?? '');

// Vacío = pólizas y fianzas.
const type = ref(props.filters.type ?? '');

const typeOptions = Object.entries(COVERAGE_TYPES).map(([value, meta]) => ({ value, label: meta.plural }));

function visit(params) {
    router.get(
        '/polizas',
        {
            ...(search.value ? { search: search.value } : {}),
            ...(type.value ? { type: type.value } : {}),
            ...params,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

let timer;

// Se espera a que deje de teclear: una petición por letra satura el servidor.
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(() => visit({}), 350);
});

// El tipo se elige de una vez: va directo y vuelve a la página 1.
watch(type, () => visit({}));

const filtering = computed(() => Boolean(search.value || type.value));

function clearFilters() {
    search.value = '';
    type.value = '';
}

function goTo(page) {
    visit({ page });
}

const range = computed(() => {
    const { current_page: page, per_page: size, total } = props.coverages.meta;
    const from = total ? (page - 1) * size + 1 : 0;

    return { from, to: from ? from + props.coverages.data.length - 1 : 0, total };
});

/** 1 … 4 5 6 … 12: la actual, sus vecinas y los extremos. */
const pages = computed(() => {
    const { current_page: current, last_page: last } = props.coverages.meta;
    const wanted = new Set([1, last, current - 1, current, current + 1].filter((n) => n >= 1 && n <= last));
    const sorted = [...wanted].sort((a, b) => a - b);

    return sorted.flatMap((n, i) => (i && n - sorted[i - 1] > 1 ? ['…', n] : [n]));
});

/* ---------- Que las 10 filas cubran el card ---------- */

/**
 * En computadora cada fila mide el alto disponible entre 10: una página llena
 * cubre el card sin hueco abajo, y una incompleta conserva ese mismo alto en
 * vez de estirar sus pocas filas. En móvil no aplica: ahí desplazar es lo normal.
 */
const DESKTOP = '(min-width: 768px)';

const tableBox = ref(null);
const rowHeight = ref(null);
let observer;

function measure() {
    const box = tableBox.value;

    if (!box || !window.matchMedia(DESKTOP).matches) {
        rowHeight.value = null;
        return;
    }

    const head = box.querySelector('thead')?.offsetHeight ?? 0;

    // Se descuenta el píxel del divisor de cada fila para no provocar scroll.
    rowHeight.value = Math.floor((box.clientHeight - head - PER_PAGE) / PER_PAGE);
}

onMounted(() => {
    observer = new ResizeObserver(measure);

    if (tableBox.value) observer.observe(tableBox.value);
});

onBeforeUnmount(() => {
    observer?.disconnect();
    clearTimeout(timer);
});

/* ---------- Resumen ---------- */

// Solo informan: son de todas las coberturas y no filtran la tabla.
const cards = computed(() => [
    {
        key: 'active',
        label: 'Vigentes',
        value: props.stats.active,
        hint: 'sin riesgo inmediato',
        icon: ShieldCheck,
        tone: COVERAGE_STATUS.active.tone,
    },
    {
        key: 'expiring',
        label: 'Por vencer',
        value: props.stats.expiring,
        hint: `en los próximos ${props.stats.warning_days} días`,
        icon: Clock,
        tone: COVERAGE_STATUS.expiring.tone,
    },
    {
        key: 'expired',
        label: 'Vencidas',
        value: props.stats.expired,
        hint: 'requieren atención',
        icon: ShieldAlert,
        tone: COVERAGE_STATUS.expired.tone,
    },
]);

/* ---------- Presentación ---------- */

const PAYMENT_LABEL = { first: '1 de 2', second: '2 de 2' };

/** Inicio y fin de la vigencia; con una sola fecha, esa. */
function validity(row) {
    const from = shortDate(row.valid_from);
    const to = shortDate(row.valid_until);

    if (!from && !to) return null;

    return from && to ? { from, to } : { from: from ?? to, to: null };
}

/**
 * Las dos cuotas de una póliza: un segmento por pago. Pagado en verde; el que
 * sigue, rojo si ya pasó su fecha límite y ámbar si no; el de después, gris.
 */
function installments(payments) {
    const steps = ['first', 'second'].map((payment) => {
        if (payments.paid?.[payment]) return 'bg-emerald-500';
        if (payments.due?.payment === payment) return payments.due.status === 'overdue' ? 'bg-red-500' : 'bg-amber-400';

        return 'bg-slate-200 dark:bg-white/10';
    });

    return {
        steps,
        label: payments.due ? PAYMENT_LABEL[payments.due.payment] : 'Pagadas',
        dueOn: payments.due ? (shortDate(payments.due.due_on) ?? 'Sin fecha límite') : null,
    };
}

const TH = 'px-3 py-2 text-left text-[0.62rem] font-bold uppercase tracking-[0.14em] whitespace-nowrap tall:py-2.5 @2xl:px-4 @6xl:px-6';
const TD = 'px-3 py-1.5 tall:py-2 @2xl:px-4 @6xl:px-6';

const ACTION =
    'grid size-7 cursor-pointer place-content-center rounded-lg transition-colors duration-150 ' +
    'focus-visible:outline-none focus-visible:ring-[3px] disabled:pointer-events-none disabled:opacity-40';

const NEUTRAL =
    'text-slate-500 hover:bg-brand/[0.07] hover:text-brand focus-visible:ring-brand/25 dark:text-brand-gray dark:hover:bg-white/10 dark:hover:text-white';

const PAGE_BTN =
    'grid h-7 min-w-7 cursor-pointer place-content-center rounded-lg px-2 text-xs font-semibold tabular-nums transition-colors duration-150 ' +
    'focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-brand/25 disabled:pointer-events-none disabled:opacity-40';
</script>

<template>
    <Head title="Pólizas y Fianzas" />

    <AppShell :breadcrumbs="breadcrumbs">
        <div class="flex flex-col md:h-full md:min-h-0">
            <!-- Encabezado -->
            <div class="mb-3 flex shrink-0 flex-wrap items-center justify-between gap-3 tall:mb-4">
                <div class="flex items-center gap-3">
                    <span
                        class="grid size-9 place-content-center rounded-xl bg-gradient-to-br from-brand-light to-brand text-white shadow-md shadow-brand/25 ring-1 ring-white/10"
                    >
                        <FileCheck class="size-4" />
                    </span>
                    <div>
                        <p class="text-[0.6rem] font-bold uppercase tracking-[0.2em] text-slate-400 dark:text-brand-gray/80">Cumplimiento</p>
                        <h1 class="text-xl font-bold tracking-tight text-brand dark:text-white">Pólizas y Fianzas</h1>
                    </div>
                </div>
            </div>

            <!-- Resumen: de todas las coberturas, no cambia con los filtros de la tabla -->
            <section aria-label="Resumen" class="mb-3 grid shrink-0 grid-cols-2 gap-2.5 tall:mb-4 sm:grid-cols-3 tall:gap-3">
                <article
                    v-for="(card, i) in cards"
                    :key="card.key"
                    class="flex items-center gap-3 rounded-2xl border border-slate-200/80 bg-white px-3.5 py-3 shadow-[0_1px_3px_rgb(2_29_73/0.04),0_8px_24px_-12px_rgb(2_29_73/0.08)] dark:border-white/[0.08] dark:bg-brand-deep dark:shadow-none"
                    :class="i === cards.length - 1 && cards.length % 2 ? 'col-span-2 sm:col-span-1' : ''"
                >
                    <span class="grid size-9 shrink-0 place-content-center rounded-xl ring-1 ring-inset" :class="card.tone">
                        <component :is="card.icon" class="size-4" />
                    </span>
                    <div class="min-w-0">
                        <p class="truncate text-[0.62rem] font-bold uppercase tracking-[0.14em] text-slate-400 dark:text-brand-gray/80">{{ card.label }}</p>
                        <p class="text-xl leading-tight font-bold text-slate-800 tabular-nums dark:text-white">{{ card.value }}</p>
                        <p class="truncate text-[0.68rem] text-slate-400 dark:text-brand-gray/70">{{ card.hint }}</p>
                    </div>
                </article>
            </section>

            <div
                class="@container flex flex-col overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_1px_3px_rgb(2_29_73/0.04),0_8px_24px_-12px_rgb(2_29_73/0.08)] md:min-h-0 md:flex-1 dark:border-white/[0.08] dark:bg-brand-deep dark:shadow-none"
            >
                <!-- Barra: búsqueda, tipo y conteo -->
                <div class="flex shrink-0 flex-wrap items-center justify-between gap-x-4 gap-y-2 px-3 py-2.5 tall:py-3 @2xl:px-4 @6xl:px-6">
                    <div class="flex w-full flex-col gap-2 @xl:w-auto @xl:flex-1 @xl:flex-row @xl:items-center">
                        <label class="relative w-full @xl:max-w-sm">
                            <span class="sr-only">Buscar pólizas y fianzas</span>
                            <input
                                v-model="search"
                                type="search"
                                placeholder="Buscar por número, proveedor, unidad o beneficiario…"
                                class="peer h-9 w-full rounded-lg border border-slate-200 bg-slate-50/70 pr-9 pl-9 text-[0.8rem] text-slate-900 outline-none transition-[border-color,background-color,box-shadow] duration-150 placeholder:text-slate-400 hover:border-slate-300 focus:border-brand/50 focus:bg-white focus:ring-4 focus:ring-brand/10 dark:border-white/10 dark:bg-white/[0.04] dark:text-white dark:placeholder:text-white/30 dark:hover:border-white/20 dark:focus:border-brand-gray/50 dark:focus:bg-white/[0.06] dark:focus:ring-white/10 [&::-webkit-search-cancel-button]:hidden"
                            />
                            <Search
                                class="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-slate-400 transition-colors peer-focus:text-brand dark:text-white/35 dark:peer-focus:text-white"
                            />
                            <button
                                v-if="search"
                                type="button"
                                class="absolute top-1/2 right-1.5 grid size-6 -translate-y-1/2 cursor-pointer place-content-center rounded-md text-slate-400 hover:bg-slate-200/60 hover:text-slate-700 dark:hover:bg-white/10 dark:hover:text-white"
                                aria-label="Limpiar búsqueda"
                                @click="search = ''"
                            >
                                <X class="size-3.5" />
                            </button>
                        </label>

                        <FilterSelect
                            v-model="type"
                            :options="typeOptions"
                            :icon="Layers"
                            placeholder="Pólizas y fianzas"
                            label="Filtrar por tipo"
                            width="@xl:w-48"
                        />
                    </div>

                    <p class="text-xs text-slate-500 dark:text-brand-gray">
                        <span class="font-bold text-slate-800 dark:text-white">{{ coverages.meta.total }}</span>
                        {{ coverages.meta.total === 1 ? 'cobertura' : 'coberturas' }}
                    </p>
                </div>

                <div ref="tableBox" class="overflow-x-auto border-t border-slate-100 md:min-h-0 md:flex-1 md:overflow-y-auto dark:border-white/[0.06]">
                    <table class="w-full text-[0.8rem]">
                        <thead class="sticky top-0 z-10 bg-slate-50 text-slate-500 dark:bg-brand-deep dark:text-brand-gray">
                            <tr>
                                <th :class="TH">No. Póliza / Fianza</th>
                                <th :class="[TH, 'hidden @2xl:table-cell']">Proveedor / Categoría</th>
                                <th :class="[TH, 'hidden @4xl:table-cell']">Vigencia</th>
                                <th :class="[TH, 'hidden text-right @xl:table-cell']">Suma asegurada</th>
                                <th :class="[TH, 'hidden @3xl:table-cell']">Cuotas</th>
                                <th :class="TH">Estado</th>
                                <th :class="[TH, 'text-right']">Acciones</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100 dark:divide-white/[0.05]">
                            <tr
                                v-for="row in coverages.data"
                                :key="row.key"
                                class="transition-colors duration-150 hover:bg-slate-50/80 dark:hover:bg-white/[0.025]"
                                :style="rowHeight ? { height: `${rowHeight}px` } : null"
                            >
                                <!-- Número y tipo -->
                                <td :class="[TD, 'max-w-0 @2xl:w-1/5']">
                                    <span class="block truncate font-semibold text-slate-800 dark:text-white" :title="row.number">{{ row.number }}</span>
                                    <span class="mt-0.5 flex items-center gap-1.5">
                                        <span class="rounded px-1.5 py-px text-[0.58rem] font-bold uppercase tracking-wider" :class="COVERAGE_TYPES[row.type].tone">
                                            {{ COVERAGE_TYPES[row.type].label }}
                                        </span>
                                        <!-- En cards angostos el proveedor baja aquí -->
                                        <span class="truncate text-[0.7rem] text-slate-400 @2xl:hidden dark:text-brand-gray/80">{{ row.provider }}</span>
                                    </span>
                                </td>

                                <!-- Proveedor y sobre qué: la unidad o el tipo de fianza -->
                                <td :class="[TD, 'hidden max-w-0 @2xl:table-cell @2xl:w-1/4']">
                                    <span
                                        class="block truncate"
                                        :class="row.provider ? 'font-semibold text-slate-800 dark:text-white' : 'text-slate-400 dark:text-brand-gray/70'"
                                    >
                                        {{ row.provider ?? 'Sin proveedor' }}
                                    </span>
                                    <component
                                        :is="row.unit_id && can('flotillas.view') ? Link : 'span'"
                                        :href="row.unit_id && can('flotillas.view') ? `/flotillas/${row.unit_id}` : undefined"
                                        class="block truncate text-[0.7rem] text-slate-500 dark:text-brand-gray/80"
                                        :class="row.unit_id && can('flotillas.view') && 'hover:text-brand hover:underline dark:hover:text-white'"
                                    >
                                        {{ [row.subject, row.detail].filter(Boolean).join(' · ') || '—' }}
                                    </component>
                                </td>

                                <!-- Vigencia -->
                                <td :class="[TD, 'hidden whitespace-nowrap tabular-nums text-slate-600 @4xl:table-cell dark:text-slate-300']">
                                    <template v-if="validity(row)">
                                        <span class="block">{{ validity(row).from }}</span>
                                        <span v-if="validity(row).to" class="block text-[0.7rem] text-slate-400 dark:text-brand-gray/80">al {{ validity(row).to }}</span>
                                    </template>
                                    <span v-else class="text-slate-400 dark:text-brand-gray/70">—</span>
                                </td>

                                <!-- Suma: costo anual con IVA en pólizas, monto en fianzas -->
                                <td :class="[TD, 'hidden text-right font-semibold whitespace-nowrap tabular-nums text-slate-800 @xl:table-cell dark:text-white']">
                                    {{ money(row.amount) }}
                                </td>

                                <!-- Cuotas: solo las pólizas pagan en dos -->
                                <td :class="[TD, 'hidden whitespace-nowrap @3xl:table-cell']">
                                    <div v-if="row.payments" class="flex flex-col items-start gap-1">
                                        <span class="flex items-center gap-2">
                                            <span class="flex gap-1" aria-hidden="true">
                                                <span
                                                    v-for="(step, index) in installments(row.payments).steps"
                                                    :key="index"
                                                    class="h-1.5 w-5 rounded-full"
                                                    :class="step"
                                                />
                                            </span>
                                            <span class="text-[0.72rem] font-semibold tabular-nums text-slate-700 dark:text-slate-200">
                                                {{ installments(row.payments).label }}
                                            </span>
                                        </span>
                                        <span v-if="installments(row.payments).dueOn" class="text-[0.68rem] tabular-nums text-slate-400 dark:text-brand-gray/80">
                                            Límite: {{ installments(row.payments).dueOn }}
                                        </span>
                                    </div>
                                    <span v-else class="text-slate-400 dark:text-brand-gray/70">—</span>
                                </td>

                                <!-- Estado de la vigencia -->
                                <td :class="[TD, 'whitespace-nowrap']">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[0.65rem] font-bold ring-1 ring-inset"
                                        :class="coverageStatus(row.status).tone"
                                    >
                                        <component :is="coverageStatus(row.status).icon" class="size-3" />
                                        {{ coverageStatus(row.status).label }}
                                    </span>
                                </td>

                                <!-- Acciones: todavía sin funcionalidad -->
                                <td :class="TD">
                                    <div class="flex items-center justify-end gap-0.5 @2xl:gap-1">
                                        <button type="button" :class="[ACTION, NEUTRAL]" :aria-label="`Ver ${row.number}`" title="Ver detalle">
                                            <Eye class="size-4" />
                                        </button>
                                        <button type="button" :class="[ACTION, NEUTRAL]" :aria-label="`Editar ${row.number}`" title="Editar">
                                            <Pencil class="size-4" />
                                        </button>
                                        <button
                                            type="button"
                                            :class="[ACTION, 'text-slate-500 hover:bg-red-50 hover:text-red-600 focus-visible:ring-red-500/25 dark:text-brand-gray dark:hover:bg-red-500/10 dark:hover:text-red-300']"
                                            :aria-label="`Eliminar ${row.number}`"
                                            title="Eliminar"
                                        >
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!coverages.data.length">
                                <td colspan="7" class="px-6 py-8 text-center tall:py-14">
                                    <span
                                        class="mx-auto mb-2.5 grid size-10 place-content-center rounded-xl bg-slate-100 text-slate-400 dark:bg-white/[0.05] dark:text-brand-gray"
                                    >
                                        <Search class="size-5" />
                                    </span>
                                    <p class="text-sm font-bold text-slate-800 dark:text-white">Sin resultados</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-brand-gray">
                                        {{ filtering ? 'Ninguna póliza o fianza coincide con la búsqueda.' : 'Aún no hay pólizas ni fianzas registradas.' }}
                                    </p>
                                    <button
                                        v-if="filtering"
                                        type="button"
                                        class="mt-3 cursor-pointer text-xs font-semibold text-brand hover:underline dark:text-white"
                                        @click="clearFilters"
                                    >
                                        Limpiar filtros
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pie: rango y paginación -->
                <div
                    v-if="coverages.data.length"
                    class="flex shrink-0 flex-wrap items-center justify-between gap-x-4 gap-y-2 border-t border-slate-100 px-3 py-2 text-xs text-slate-500 tall:py-2.5 @2xl:px-4 @6xl:px-6 dark:border-white/[0.06] dark:text-brand-gray"
                >
                    <p>
                        Mostrando
                        <span class="font-semibold text-slate-800 tabular-nums dark:text-white">{{ range.from }}–{{ range.to }}</span>
                        de
                        <span class="font-semibold text-slate-800 tabular-nums dark:text-white">{{ range.total }}</span>
                    </p>

                    <nav v-if="coverages.meta.last_page > 1" aria-label="Paginación" class="flex items-center gap-1">
                        <button
                            type="button"
                            :class="[PAGE_BTN, 'hover:bg-slate-100 hover:text-brand dark:hover:bg-white/[0.06] dark:hover:text-white']"
                            :disabled="coverages.meta.current_page <= 1"
                            aria-label="Página anterior"
                            @click="goTo(coverages.meta.current_page - 1)"
                        >
                            <ChevronLeft class="size-3.5" />
                        </button>

                        <template v-for="(n, i) in pages" :key="`${n}-${i}`">
                            <span v-if="n === '…'" class="px-1 text-slate-400">…</span>
                            <button
                                v-else
                                type="button"
                                :class="[
                                    PAGE_BTN,
                                    n === coverages.meta.current_page
                                        ? 'bg-brand text-white shadow-md shadow-brand/25 dark:bg-brand-light'
                                        : 'hover:bg-slate-100 hover:text-brand dark:hover:bg-white/[0.06] dark:hover:text-white',
                                ]"
                                :aria-current="n === coverages.meta.current_page ? 'page' : undefined"
                                @click="goTo(n)"
                            >
                                {{ n }}
                            </button>
                        </template>

                        <button
                            type="button"
                            :class="[PAGE_BTN, 'hover:bg-slate-100 hover:text-brand dark:hover:bg-white/[0.06] dark:hover:text-white']"
                            :disabled="coverages.meta.current_page >= coverages.meta.last_page"
                            aria-label="Página siguiente"
                            @click="goTo(coverages.meta.current_page + 1)"
                        >
                            <ChevronRight class="size-3.5" />
                        </button>
                    </nav>
                </div>
            </div>
        </div>
    </AppShell>
</template>
