<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { Building2, ChevronDown, ChevronLeft, ChevronRight, Eye, ListFilter, Pencil, Plus, Search, Store, Tractor, Trash2, Truck, Wallet, Wrench, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppShell from '@/Layouts/AppShell.vue';
import ConfirmDeleteDialog from '@/components/app/ConfirmDeleteDialog.vue';
import FilterSelect from '@/components/app/FilterSelect.vue';
import PolicyMachinesTable from '@/components/app/PolicyMachinesTable.vue';
import { usePermissions } from '@/composables/usePermissions';
import { coverageStatus } from '@/lib/coverages';
import { MACHINERY_COVERAGES, PAYMENT_WARNING_DAYS, UNASSIGNED, UNIT_STATUS, UNIT_TYPES, money, shortDate, unitStatus } from '@/lib/units';

const props = defineProps({
    /** 'vehicles' | 'machinery' | 'supplier': qué pestaña se ve. */
    tab: { type: String, default: 'vehicles' },
    /**
     * { data, meta }. En automóviles, una unidad por fila ({ id, brand, model,
     * status, semester, annual, ... }); en maquinaria, una póliza por fila
     * ({ id, policy, coverage, currency, amount, valid_until, status, machines });
     * en preferentes, una póliza por fila ({ id, kind, unit_id, brand, model,
     * supplier, machines, ... }): la del automóvil o la de maquinaria.
     */
    rows: { type: Object, required: true },
    /** { total, expiring, maintenance } — de toda la flotilla, sin filtros. */
    stats: { type: Object, required: true },
    /** { search, status, brand, business_unit } — lo que ya viene aplicado. */
    filters: { type: Object, default: () => ({}) },
    /** { brands: [] } de lo capturado; { businessUnits: [{ value, label }] } del catálogo. */
    options: { type: Object, default: () => ({ brands: [], businessUnits: [] }) },
});

const breadcrumbs = [{ label: 'Inicio', href: '/calendario' }, { label: 'Flotillas' }];

/* ---------- Pestañas ---------- */

const TABS = [
    { value: 'vehicles', label: 'AUTOMÓVILES', icon: Truck },
    { value: 'machinery', label: 'MAQUINARIA', icon: Tractor },
    { value: 'supplier', label: 'PREFERENTES', icon: Store },
];

const machinery = computed(() => props.tab === 'machinery');

/** Las unidades con póliza del proveedor: automóviles y máquinas, aparte. */
const supplier = computed(() => props.tab === 'supplier');

/** A dónde lleva cada fila de preferentes: la maquinaria a su póliza, el automóvil a su unidad. */
const supplierUrl = (row) => (row.kind === 'machinery' ? `/flotillas/maquinaria/${row.id}` : `/flotillas/${row.unit_id}`);

/** Lo que cubre la fila: el automóvil, o «3 máquinas» en la de maquinaria. */
const supplierName = (row) => (row.kind === 'machinery' ? plural(row.machines.length, 'máquina', 'máquinas') : `${row.brand} ${row.model}`);

/** El alta: desde maquinaria abre en maquinaria; desde proveedor, con la póliza del proveedor. */
const createUrl = computed(() =>
    supplier.value ? '/flotillas/crear?origen=supplier' : machinery.value ? '/flotillas/crear?tipo=machinery' : '/flotillas/crear',
);

/** Cambiar de pestaña conserva los filtros y vuelve a la página 1. */
function switchTab(value) {
    if (value !== props.tab) visit({ tab: value });
}

/** Las pólizas de maquinaria abiertas (en su pestaña y en preferentes): muestran sus máquinas debajo. */
const expanded = ref(new Set());

function toggleRow(id) {
    const next = new Set(expanded.value);

    next.has(id) ? next.delete(id) : next.add(id);
    expanded.value = next;
}

const { can } = usePermissions();

/* ---------- Eliminar ---------- */

// El modal de confirmación es el mismo de toda la app.
const deleteOpen = ref(false);
const toDelete = ref(null);
const deleting = ref(null);

function askDelete(row) {
    toDelete.value = row;
    deleteOpen.value = true;
}

function destroy() {
    const row = toDelete.value;

    // La fila se apaga mientras el servidor borra: también quita sus eventos
    // de Outlook y sus archivos, así que no es inmediato.
    deleting.value = row.id;

    const url = machinery.value
        ? `/flotillas/maquinaria/${row.id}`
        : supplier.value
          ? row.kind === 'machinery'
              ? `/flotillas/maquinaria/${row.id}`
              : `/flotillas/${row.unit_id}`
          : `/flotillas/${row.id}`;

    router.delete(url, {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
}

/* ---------- Búsqueda, estado y páginas ---------- */

// El servidor pagina de 10 en 10, igual que las demás tablas.
const PER_PAGE = 10;

const search = ref(props.filters.search ?? '');

// Los de lista: se eligen, no se escriben. Vacío = sin filtrar.
const status = ref(props.filters.status ?? '');
const brand = ref(props.filters.brand ?? '');
const businessUnit = ref(props.filters.business_unit ?? '');

function visit(params) {
    router.get(
        '/flotillas',
        {
            ...(props.tab !== 'vehicles' ? { tab: props.tab } : {}),
            ...(search.value ? { search: search.value } : {}),
            ...(status.value ? { status: status.value } : {}),
            ...(brand.value ? { brand: brand.value } : {}),
            ...(businessUnit.value ? { business_unit: businessUnit.value } : {}),
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

// Los de lista se eligen de una vez: van directo. Cambiar cualquiera vuelve a
// la página 1, porque `visit` no arrastra la que estaba.
watch([status, brand, businessUnit], () => visit({}));

const filtering = computed(() => Boolean(search.value || status.value || brand.value || businessUnit.value));

function clearFilters() {
    search.value = '';
    status.value = '';
    brand.value = '';
    businessUnit.value = '';
}

/** Lo aplicado, en fichas: se ve de un vistazo y cada una se quita sola. */
const activeFilters = computed(() =>
    [
        { key: 'status', label: statusOptions.find((o) => o.value === status.value)?.label, clear: () => (status.value = '') },
        { key: 'brand', label: brand.value, clear: () => (brand.value = '') },
        {
            key: 'business_unit',
            label: props.options.businessUnits.find((o) => o.value === businessUnit.value)?.label,
            clear: () => (businessUnit.value = ''),
        },
    ].filter((chip) => chip.label),
);

/* ---------- Opciones de los filtros ---------- */

// El estado es el único con catálogo fijo; la marca sale de lo capturado.
const statusOptions = Object.entries(UNIT_STATUS).map(([value, meta]) => ({ value, label: meta.label, dot: meta.dot }));

const toOptions = (values) => values.map((value) => ({ value, label: value }));

const brandOptions = computed(() => toOptions(props.options.brands));

// Las de negocio ya vienen como { value: id, label: nombre }: se filtra por id.
const businessUnitOptions = computed(() => props.options.businessUnits);

function goTo(page) {
    visit({ page });
}

const range = computed(() => {
    const { current_page: page, per_page: size, total } = props.rows.meta;
    const from = total ? (page - 1) * size + 1 : 0;

    return { from, to: from ? from + props.rows.data.length - 1 : 0, total };
});

/** 1 … 4 5 6 … 12: la actual, sus vecinas y los extremos. */
const pages = computed(() => {
    const { current_page: current, last_page: last } = props.rows.meta;
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

/* ---------- Presentación ---------- */

const statusOf = unitStatus;

/** «Nissan NP300»: así se nombra la unidad en avisos y etiquetas. */
const unitName = (unit) => `${unit.brand} ${unit.model}`;

/** Lo que se pregunta antes de borrar: la unidad, o la póliza con sus máquinas. */
const deleteText = computed(() => {
    const row = toDelete.value;

    if (!row) return '';

    if (supplier.value) {
        return row.kind === 'machinery'
            ? `¿Seguro que quieres eliminar la póliza «${row.policy}»? También se van sus ${supplierName(row)}, sus documentos y sus eventos del calendario.`
            : `¿Seguro que quieres eliminar la unidad «${unitName(row)}»? También se van sus pólizas, evidencias y eventos del calendario.`;
    }

    return machinery.value
        ? `¿Seguro que quieres eliminar la póliza «${row.policy}»? También se van sus ${row.machines.length} máquinas, sus documentos y sus eventos del calendario.`
        : `¿Seguro que quieres eliminar la unidad «${unitName(row)}»? También se van sus pólizas, evidencias y eventos del calendario.`;
});

/** «3 máquinas»: en la tabla de maquinaria cada fila es una póliza. */
const plural = (count, one, many) => `${count} ${count === 1 ? one : many}`;

/* ---------- Resumen ---------- */

// Solo informan: son de toda la flotilla y no filtran la tabla.
const cards = computed(() => [
    {
        key: 'total',
        label: 'Total de unidades',
        value: props.stats.total,
        hint: props.stats.total === 1 ? 'unidad' : 'unidades',
        icon: Truck,
        tone: 'bg-brand/[0.07] text-brand ring-brand/15 dark:bg-white/10 dark:text-white dark:ring-white/10',
    },
    {
        key: 'expiring',
        label: 'Vigencias por vencer',
        value: props.stats.expiring,
        hint: `en los próximos ${PAYMENT_WARNING_DAYS} días`,
        icon: Wallet,
        tone: UNIT_STATUS.maintenance.tone,
    },
    {
        key: 'maintenance',
        label: 'Fuera de servicio',
        value: props.stats.maintenance,
        hint: 'en mantenimiento',
        icon: Wrench,
        tone: 'bg-slate-50 text-slate-600 ring-slate-500/15 dark:bg-white/[0.05] dark:text-brand-gray dark:ring-white/10',
    },
]);

/* Mismas medidas que la tabla de licencias. */
const TH = 'px-3 py-2 text-left text-[0.68rem] font-bold whitespace-nowrap tall:py-2.5 @2xl:px-4 @6xl:px-6';
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
    <Head title="Flotillas" />

    <AppShell :breadcrumbs="breadcrumbs">
        <div class="flex flex-col md:h-full md:min-h-0">
            <!-- Encabezado -->
            <div class="mb-3 flex shrink-0 flex-wrap items-center justify-between gap-3 tall:mb-4">
                <div class="flex items-center gap-3">
                    <span
                        class="grid size-9 place-content-center rounded-xl bg-gradient-to-br from-brand-light to-brand text-white shadow-md shadow-brand/25 ring-1 ring-white/10"
                    >
                        <Truck class="size-4" />
                    </span>
                    <div>
                        <h1 class="text-xl font-bold tracking-tight text-brand dark:text-white">Flotillas</h1>
                    </div>
                </div>

                <Link
                    v-if="can('flotillas.create')"
                    :href="createUrl"
                    class="inline-flex h-9 items-center gap-2 rounded-xl bg-brand px-4 text-[0.8rem] font-semibold text-white shadow-md shadow-brand/25 transition-all duration-150 hover:bg-brand/90 hover:shadow-lg hover:shadow-brand/30 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/25 active:translate-y-px dark:bg-brand-light dark:shadow-black/30 dark:hover:bg-brand-light/90"
                >
                    <Plus class="size-4" />
                    {{ machinery ? 'Nueva póliza de maquinaria' : 'Nueva unidad' }}
                </Link>
            </div>

            <!-- Resumen: de toda la flotilla, no cambia con los filtros de la tabla -->
            <!-- En teléfono van de dos en dos y la tercera ocupa el ancho: tres apiladas comen toda la pantalla -->
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
                        <p class="truncate text-[0.62rem] font-bold text-slate-400 dark:text-brand-gray/80">{{ card.label }}</p>
                        <p class="text-xl leading-tight font-bold text-slate-800 tabular-nums dark:text-white">{{ card.value }}</p>
                        <p class="truncate text-[0.68rem] text-slate-400 dark:text-brand-gray/70">{{ card.hint }}</p>
                    </div>
                </article>
            </section>

            <div
                class="@container flex flex-col overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_1px_3px_rgb(2_29_73/0.04),0_8px_24px_-12px_rgb(2_29_73/0.08)] md:min-h-0 md:flex-1 dark:border-white/[0.08] dark:bg-brand-deep dark:shadow-none"
            >
                <!--
                    Barra: en teléfono el buscador arriba y los filtros de dos en dos; desde
                    @xl todo cabe en una fila. Debajo, lo aplicado.
                -->
                <div class="flex shrink-0 flex-col gap-2 px-3 py-2.5 tall:py-3 @2xl:px-4 @6xl:px-6">
                    <!-- Pestañas: automóviles por unidad, maquinaria por póliza -->
                    <div class="flex gap-1 rounded-xl bg-slate-100 p-1 self-start dark:bg-white/[0.05]" role="tablist" aria-label="Tipo de unidad">
                        <button
                            v-for="option in TABS"
                            :key="option.value"
                            type="button"
                            role="tab"
                            :aria-selected="tab === option.value"
                            class="inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-lg px-3 text-[0.78rem] font-semibold transition-colors duration-200 focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-brand/25"
                            :class="
                                tab === option.value
                                    ? 'bg-white text-brand shadow-sm dark:bg-white/15 dark:text-white'
                                    : 'text-slate-500 hover:text-slate-800 dark:text-brand-gray dark:hover:text-white'
                            "
                            @click="switchTab(option.value)"
                        >
                            <component :is="option.icon" class="size-3.5" />
                            {{ option.label }}
                        </button>
                    </div>

                    <div class="grid grid-cols-2 items-center gap-2 @xl:flex @xl:flex-wrap">
                        <label class="relative col-span-2 min-w-0 @xl:max-w-xs @xl:flex-1">
                            <span class="sr-only">Buscar unidades</span>
                            <input
                                v-model="search"
                                type="search"
                                :placeholder="
                                    supplier
                                        ? 'Buscar por póliza, proveedor, marca o placa…'
                                        : machinery
                                          ? 'Buscar por póliza, aseguradora, tipo, marca o # económico…'
                                          : 'Buscar por póliza, marca, placa o asignación…'
                                "
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
                            v-model="status"
                            :options="statusOptions"
                            placeholder="Todos los estados"
                            label="Filtrar por estado"
                            width="@xl:w-48"
                        />

                        <FilterSelect
                            v-model="brand"
                            :options="brandOptions"
                            :icon="Truck"
                            placeholder="Todas las marcas"
                            label="Filtrar por marca"
                            width="@xl:w-48"
                        />

                        <FilterSelect
                            v-model="businessUnit"
                            :options="businessUnitOptions"
                            :icon="Building2"
                            placeholder="Todas las unidades de negocio"
                            label="Filtrar por unidad de negocio"
                            width="@xl:w-56"
                        />

                        <p class="col-span-2 shrink-0 text-xs text-slate-500 @xl:col-span-1 @xl:ml-auto dark:text-brand-gray">
                            <span class="font-bold text-slate-800 dark:text-white">{{ range.total }}</span>
                            {{ machinery ? (range.total === 1 ? 'póliza' : 'pólizas') : range.total === 1 ? 'unidad' : 'unidades' }}
                        </p>
                    </div>

                    <!-- Lo aplicado, incluida la búsqueda: quitar uno no obliga a abrir su lista -->
                    <div v-if="filtering" class="flex flex-wrap items-center gap-1.5">
                        <ListFilter class="size-3.5 shrink-0 text-slate-400 dark:text-brand-gray/70" />

                        <span
                            v-if="search"
                            class="inline-flex max-w-[14rem] items-center gap-1 rounded-md bg-slate-100 py-0.5 pr-1 pl-2 text-[0.72rem] font-medium text-slate-600 dark:bg-white/[0.07] dark:text-white"
                        >
                            <span class="truncate">«{{ search }}»</span>
                            <button
                                type="button"
                                class="grid size-4 shrink-0 cursor-pointer place-content-center rounded text-slate-400 hover:bg-slate-200 hover:text-slate-700 dark:hover:bg-white/15 dark:hover:text-white"
                                aria-label="Quitar la búsqueda"
                                @click="search = ''"
                            >
                                <X class="size-3" />
                            </button>
                        </span>

                        <span
                            v-for="chip in activeFilters"
                            :key="chip.key"
                            class="inline-flex max-w-[14rem] items-center gap-1 rounded-md bg-brand/[0.07] py-0.5 pr-1 pl-2 text-[0.72rem] font-semibold text-brand ring-1 ring-inset ring-brand/15 dark:bg-white/10 dark:text-white dark:ring-white/10"
                        >
                            <span class="truncate">{{ chip.label }}</span>
                            <button
                                type="button"
                                class="grid size-4 shrink-0 cursor-pointer place-content-center rounded text-brand/60 hover:bg-brand/15 hover:text-brand dark:text-white/60 dark:hover:bg-white/15 dark:hover:text-white"
                                :aria-label="`Quitar el filtro ${chip.label}`"
                                @click="chip.clear()"
                            >
                                <X class="size-3" />
                            </button>
                        </span>

                        <button
                            type="button"
                            class="ml-1 cursor-pointer rounded-md px-1.5 py-0.5 text-[0.72rem] font-semibold text-slate-500 underline-offset-2 transition-colors duration-150 hover:bg-slate-100 hover:text-slate-800 hover:underline focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/10 dark:text-brand-gray dark:hover:bg-white/[0.06] dark:hover:text-white"
                            @click="clearFilters"
                        >
                            Limpiar todo
                        </button>
                    </div>
                </div>

                <!--
                    Cuando el card se angosta, la tabla no se encoge: se desplaza de lado. El
                    `min-w` es lo que mide con todas sus columnas, así nada se recorta ni se
                    esconde y las acciones siempre se alcanzan.
                -->
                <div
                    ref="tableBox"
                    class="overflow-x-auto border-t border-slate-100 md:min-h-0 md:flex-1 md:overflow-y-auto dark:border-white/[0.06]"
                >
                    <!-- Maquinaria: una póliza por fila; sus máquinas se despliegan debajo -->
                    <table v-if="machinery" class="w-full min-w-[62rem] text-[0.8rem]">
                        <thead class="sticky top-0 z-10 bg-slate-50 text-slate-500 dark:bg-brand-deep dark:text-brand-gray">
                            <tr>
                                <th :class="TH">Póliza</th>
                                <th :class="TH">Aseguradora</th>
                                <th :class="[TH, 'w-px']">Cobertura</th>
                                <th :class="[TH, 'w-px']">Máquinas</th>
                                <th :class="[TH, 'w-px text-right']">Monto</th>
                                <th :class="[TH, 'w-px']">Vigencia</th>
                                <th :class="[TH, 'w-px text-right']">Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            <template v-for="row in rows.data" :key="row.id">
                                <tr
                                    class="border-t border-slate-100 transition-colors duration-150 first:border-t-0 hover:bg-slate-50/80 dark:border-white/[0.05] dark:hover:bg-white/[0.025]"
                                    :class="[deleting === row.id && 'pointer-events-none opacity-40', expanded.has(row.id) && 'bg-slate-50/60 dark:bg-white/[0.02]']"
                                    :style="rowHeight ? { height: `${rowHeight}px` } : null"
                                >
                                    <td :class="[TD, 'whitespace-nowrap']">
                                        <span class="font-semibold text-slate-800 dark:text-white">{{ row.policy }}</span>
                                    </td>

                                    <td :class="[TD, 'w-full max-w-0']">
                                        <span class="block truncate text-slate-700 dark:text-slate-200" :title="row.insurer">{{ row.insurer ?? '—' }}</span>
                                        <span class="block truncate text-[0.7rem] text-slate-500 dark:text-brand-gray">{{ row.business_unit ?? '—' }}</span>
                                    </td>

                                    <td :class="[TD, 'w-px whitespace-nowrap']">
                                        <span
                                            class="inline-flex items-center rounded-md bg-brand/[0.07] px-1.5 py-0.5 text-[0.7rem] font-bold text-brand dark:bg-white/10 dark:text-white"
                                            :title="MACHINERY_COVERAGES[row.coverage]?.label"
                                        >
                                            {{ MACHINERY_COVERAGES[row.coverage]?.short ?? '—' }}
                                        </span>
                                    </td>

                                    <!-- Despliega las máquinas de la póliza -->
                                    <td :class="[TD, 'w-px whitespace-nowrap']">
                                        <button
                                            type="button"
                                            class="inline-flex cursor-pointer items-center gap-1 rounded-md px-1.5 py-0.5 text-[0.75rem] font-semibold text-slate-600 transition-colors hover:bg-brand/[0.07] hover:text-brand focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-brand/25 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white"
                                            :aria-expanded="expanded.has(row.id)"
                                            @click="toggleRow(row.id)"
                                        >
                                            {{ plural(row.machines.length, 'máquina', 'máquinas') }}
                                            <ChevronDown class="size-3.5 transition-transform duration-200" :class="expanded.has(row.id) && 'rotate-180'" />
                                        </button>
                                    </td>

                                    <td :class="[TD, 'w-px whitespace-nowrap text-right font-semibold text-slate-700 tabular-nums dark:text-slate-200']">
                                        {{ row.amount !== null ? money(row.amount, row.currency ?? 'MXN') : '—' }}
                                    </td>

                                    <td :class="[TD, 'w-px whitespace-nowrap']">
                                        <div class="flex flex-col items-start gap-0.5">
                                            <span class="text-[0.75rem] font-semibold text-slate-700 tabular-nums dark:text-slate-200">{{ shortDate(row.valid_until) ?? 'Sin fecha' }}</span>
                                            <span class="inline-flex items-center rounded-md px-1.5 py-px text-[0.65rem] font-bold ring-1 ring-inset" :class="coverageStatus(row.status).tone">
                                                {{ coverageStatus(row.status).label }}
                                            </span>
                                        </div>
                                    </td>

                                    <td :class="[TD, 'w-px']">
                                        <div class="flex items-center justify-end gap-0.5 @2xl:gap-1">
                                            <Link :href="`/flotillas/maquinaria/${row.id}`" :class="[ACTION, NEUTRAL]" :aria-label="`Ver póliza ${row.policy}`" title="Ver detalle">
                                                <Eye class="size-4" />
                                            </Link>
                                            <Link
                                                v-if="can('flotillas.update')"
                                                :href="`/flotillas/maquinaria/${row.id}/editar`"
                                                :class="[ACTION, NEUTRAL]"
                                                :aria-label="`Editar póliza ${row.policy}`"
                                                title="Editar"
                                            >
                                                <Pencil class="size-4" />
                                            </Link>
                                            <button
                                                v-if="can('flotillas.delete')"
                                                type="button"
                                                :class="[ACTION, 'text-slate-500 hover:bg-red-50 hover:text-red-600 focus-visible:ring-red-500/25 dark:text-brand-gray dark:hover:bg-red-500/10 dark:hover:text-red-300']"
                                                :aria-label="`Eliminar póliza ${row.policy}`"
                                                title="Eliminar"
                                                :disabled="deleting === row.id"
                                                @click="askDelete(row)"
                                            >
                                                <Trash2 class="size-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Las máquinas de la póliza -->
                                <tr v-if="expanded.has(row.id)" class="bg-slate-50/60 dark:bg-white/[0.02]">
                                    <td colspan="7" class="px-3 pt-0 pb-3 @2xl:px-4 @6xl:px-6">
                                        <PolicyMachinesTable :machines="row.machines" />
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    <!-- Preferentes: una póliza por fila; la del automóvil con su unidad, la de maquinaria con todas sus máquinas -->
                    <table v-else-if="supplier" class="w-full min-w-[62rem] text-[0.8rem]">
                        <thead class="sticky top-0 z-10 bg-slate-50 text-slate-500 dark:bg-brand-deep dark:text-brand-gray">
                            <tr>
                                <th :class="[TH, 'w-px']">Tipo</th>
                                <th :class="TH">Unidad</th>
                                <th :class="[TH, 'w-px']">Placas / # Eco</th>
                                <th :class="[TH, 'w-px']">Póliza</th>
                                <th :class="TH">Beneficiario preferente</th>
                                <th :class="[TH, 'w-px']">Estado</th>
                                <th :class="[TH, 'w-px']">Vigencia</th>
                                <th :class="[TH, 'w-px text-right']">Acciones</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100 dark:divide-white/[0.05]">
                            <template v-for="row in rows.data" :key="row.id">
                                <tr
                                    class="transition-colors duration-150 hover:bg-slate-50/80 dark:hover:bg-white/[0.025]"
                                    :class="[deleting === row.id && 'pointer-events-none opacity-40', expanded.has(row.id) && 'bg-slate-50/60 dark:bg-white/[0.02]']"
                                    :style="rowHeight ? { height: `${rowHeight}px` } : null"
                                >
                                    <td :class="[TD, 'w-px whitespace-nowrap']">
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="grid size-7 shrink-0 place-content-center rounded-lg bg-slate-50 text-slate-500 ring-1 ring-inset ring-slate-500/15 dark:bg-white/[0.05] dark:text-brand-gray dark:ring-white/10"
                                            >
                                                <component :is="UNIT_TYPES[row.kind]?.icon ?? Truck" class="size-3.5" />
                                            </span>
                                            <span class="text-slate-600 dark:text-slate-300">{{ UNIT_TYPES[row.kind]?.label.toUpperCase() ?? '—' }}</span>
                                        </div>
                                    </td>

                                    <td :class="[TD, 'w-full max-w-0']">
                                        <!-- La de maquinaria despliega sus máquinas debajo, como en su pestaña -->
                                        <button
                                            v-if="row.kind === 'machinery'"
                                            type="button"
                                            class="inline-flex cursor-pointer items-center gap-1 rounded-md px-1.5 py-0.5 -ml-1.5 text-[0.8rem] font-semibold text-slate-800 transition-colors hover:bg-brand/[0.07] hover:text-brand focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-brand/25 dark:text-white dark:hover:bg-white/10"
                                            :aria-expanded="expanded.has(row.id)"
                                            @click="toggleRow(row.id)"
                                        >
                                            {{ supplierName(row) }}
                                            <ChevronDown class="size-3.5 transition-transform duration-200" :class="expanded.has(row.id) && 'rotate-180'" />
                                        </button>
                                        <span v-else class="block truncate font-semibold text-slate-800 dark:text-white" :title="supplierName(row)">{{ supplierName(row) }}</span>
                                        <span class="block truncate text-[0.7rem] text-slate-500 dark:text-brand-gray">{{ row.business_unit ?? '—' }}</span>
                                    </td>

                                    <td :class="[TD, 'w-px whitespace-nowrap text-slate-600 tabular-nums dark:text-slate-300']">
                                        {{ row.kind === 'machinery' ? '—' : (row.plate ?? row.economic_number ?? '—') }}
                                    </td>

                                    <td :class="[TD, 'w-px whitespace-nowrap font-semibold text-slate-800 dark:text-white']">{{ row.policy ?? '—' }}</td>

                                    <td :class="TD">
                                        <span class="block max-w-[12rem] truncate text-slate-700 dark:text-slate-200" :title="row.supplier">{{ row.supplier ?? '—' }}</span>
                                    </td>

                                    <td :class="[TD, 'w-px']">
                                        <!-- Si las máquinas no van todas igual, no hay un solo estado -->
                                        <span
                                            v-if="row.status"
                                            class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[0.7rem] font-bold whitespace-nowrap ring-1 ring-inset"
                                            :class="statusOf(row.status).tone"
                                        >
                                            {{ statusOf(row.status).label }}
                                        </span>
                                        <span v-else class="text-[0.75rem] text-slate-500 dark:text-brand-gray">Varios</span>
                                    </td>

                                    <td :class="[TD, 'w-px whitespace-nowrap']">
                                        <div class="flex flex-col items-start gap-0.5">
                                            <span class="text-[0.75rem] font-semibold text-slate-700 tabular-nums dark:text-slate-200">{{ shortDate(row.valid_until) ?? 'Sin fecha' }}</span>
                                            <span
                                                v-if="row.coverage_status"
                                                class="inline-flex items-center rounded-md px-1.5 py-px text-[0.65rem] font-bold ring-1 ring-inset"
                                                :class="coverageStatus(row.coverage_status).tone"
                                            >
                                                {{ coverageStatus(row.coverage_status).label }}
                                            </span>
                                        </div>
                                    </td>

                                    <td :class="[TD, 'w-px']">
                                        <div class="flex items-center justify-end gap-0.5 @2xl:gap-1">
                                            <Link :href="supplierUrl(row)" :class="[ACTION, NEUTRAL]" :aria-label="`Ver ${supplierName(row)}`" title="Ver detalle">
                                                <Eye class="size-4" />
                                            </Link>
                                            <Link
                                                v-if="can('flotillas.update')"
                                                :href="`${supplierUrl(row)}/editar`"
                                                :class="[ACTION, NEUTRAL]"
                                                :aria-label="`Editar ${supplierName(row)}`"
                                                title="Editar"
                                            >
                                                <Pencil class="size-4" />
                                            </Link>
                                            <button
                                                v-if="can('flotillas.delete')"
                                                type="button"
                                                :class="[ACTION, 'text-slate-500 hover:bg-red-50 hover:text-red-600 focus-visible:ring-red-500/25 dark:text-brand-gray dark:hover:bg-red-500/10 dark:hover:text-red-300']"
                                                :aria-label="`Eliminar ${supplierName(row)}`"
                                                title="Eliminar"
                                                :disabled="deleting === row.id"
                                                @click="askDelete(row)"
                                            >
                                                <Trash2 class="size-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Las máquinas de la póliza de maquinaria -->
                                <tr v-if="row.kind === 'machinery' && expanded.has(row.id)" class="bg-slate-50/60 dark:bg-white/[0.02]">
                                    <td colspan="8" class="px-3 pt-0 pb-3 @2xl:px-4 @6xl:px-6">
                                        <PolicyMachinesTable :machines="row.machines" />
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    <table v-else class="w-full min-w-[62rem] text-[0.8rem]">
                        <thead class="sticky top-0 z-10 bg-slate-50 text-slate-500 dark:bg-brand-deep dark:text-brand-gray">
                            <tr>
                                <th :class="[TH, 'w-px']">Tipo</th>
                                <th :class="TH">Marca</th>
                                <th :class="TH">Modelo</th>
                                <th :class="[TH, 'w-px']">Placas</th>
                                <th :class="TH">Asignación</th>
                                <th :class="[TH, 'w-px']">Estado</th>
                                <!-- Estas dos salen de la póliza vigente, no de la unidad -->
                                <th :class="[TH, 'w-px']">Vigencia semestral</th>
                                <th :class="[TH, 'w-px']">Vigencia anual</th>
                                <th :class="[TH, 'w-px text-right']">Acciones</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100 dark:divide-white/[0.05]">
                            <tr
                                v-for="unit in rows.data"
                                :key="unit.id"
                                class="transition-colors duration-150 hover:bg-slate-50/80 dark:hover:bg-white/[0.025]"
                                :class="deleting === unit.id && 'pointer-events-none opacity-40'"
                                :style="rowHeight ? { height: `${rowHeight}px` } : null"
                            >
                                <!-- Tipo: vehículo o maquinaria -->
                                <td :class="[TD, 'w-px whitespace-nowrap']">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="grid size-7 shrink-0 place-content-center rounded-lg bg-slate-50 text-slate-500 ring-1 ring-inset ring-slate-500/15 dark:bg-white/[0.05] dark:text-brand-gray dark:ring-white/10"
                                        >
                                            <component :is="UNIT_TYPES[unit.type]?.icon ?? Truck" class="size-3.5" />
                                        </span>
                                        <span class="text-slate-600 dark:text-slate-300">{{ UNIT_TYPES[unit.type]?.label.toUpperCase() ?? '—' }}</span>
                                    </div>
                                </td>

                                <td :class="[TD, 'whitespace-nowrap']">
                                    <span class="font-semibold text-slate-800 dark:text-white">{{ unit.brand }}</span>
                                </td>

                                <!-- La columna elástica: si falta lugar, es la que cede -->
                                <td :class="[TD, 'w-full max-w-0']">
                                    <span class="block truncate text-slate-700 dark:text-slate-200" :title="unit.model">{{ unit.model }}</span>
                                </td>

                                <td :class="[TD, 'w-px whitespace-nowrap text-slate-600 tabular-nums dark:text-slate-300']">
                                    {{ unit.plate ?? '—' }}
                                </td>

                                <td :class="TD">
                                    <span class="block max-w-[12rem] truncate text-slate-600 dark:text-slate-300" :title="unit.responsible">{{ unit.responsible ?? '—' }}</span>
                                </td>

                                <td :class="[TD, 'w-px']">
                                    <span
                                        class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[0.7rem] font-bold whitespace-nowrap ring-1 ring-inset"
                                        :class="statusOf(unit.status).tone"
                                    >
                                        {{ statusOf(unit.status).label }}
                                    </span>
                                </td>

                                <!--
                                    Semestral: el cierre del semestre en curso. Anual: el fin de la
                                    vigencia. Arriba la fecha y abajo cómo va: vigente, por vencer o
                                    vencida. Sin póliza quedan por asignar.
                                -->
                                <td v-for="payment in [unit.semester, unit.annual]" :key="payment === unit.annual ? 'annual' : 'semester'" :class="[TD, 'w-px whitespace-nowrap']">
                                    <span v-if="!payment" class="text-[0.72rem] font-medium text-slate-400 italic dark:text-brand-gray/70">{{ UNASSIGNED }}</span>
                                    <div v-else class="flex flex-col items-start gap-0.5">
                                        <span class="text-[0.75rem] font-semibold text-slate-700 tabular-nums dark:text-slate-200">{{ shortDate(payment.ends_on) ?? 'Sin fecha' }}</span>
                                        <span
                                            class="inline-flex items-center rounded-md px-1.5 py-px text-[0.65rem] font-bold ring-1 ring-inset"
                                            :class="coverageStatus(payment.status).tone"
                                        >
                                            {{ coverageStatus(payment.status).label }}
                                        </span>
                                    </div>
                                </td>

                                <td :class="[TD, 'w-px']">
                                    <div class="flex items-center justify-end gap-0.5 @2xl:gap-1">
                                        <Link :href="`/flotillas/${unit.id}`" :class="[ACTION, NEUTRAL]" :aria-label="`Ver ${unitName(unit)}`" title="Ver detalle">
                                            <Eye class="size-4" />
                                        </Link>
                                        <Link
                                            v-if="can('flotillas.update')"
                                            :href="`/flotillas/${unit.id}/editar`"
                                            :class="[ACTION, NEUTRAL]"
                                            :aria-label="`Editar ${unitName(unit)}`"
                                            title="Editar"
                                        >
                                            <Pencil class="size-4" />
                                        </Link>
                                        <button
                                            v-if="can('flotillas.delete')"
                                            type="button"
                                            :class="[ACTION, 'text-slate-500 hover:bg-red-50 hover:text-red-600 focus-visible:ring-red-500/25 dark:text-brand-gray dark:hover:bg-red-500/10 dark:hover:text-red-300']"
                                            :aria-label="`Eliminar ${unitName(unit)}`"
                                            title="Eliminar"
                                            :disabled="deleting === unit.id"
                                            @click="askDelete(unit)"
                                        >
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>

                        </tbody>
                    </table>

                    <!-- Estado vacío, el mismo para la tabla y para las tarjetas -->
                    <div v-if="!rows.data.length" class="px-6 py-8 text-center tall:py-14">
                        <span class="mx-auto mb-2.5 grid size-10 place-content-center rounded-xl bg-slate-100 text-slate-400 dark:bg-white/[0.05] dark:text-brand-gray">
                            <component :is="filtering ? Search : supplier ? Store : machinery ? Tractor : Truck" class="size-5" />
                        </span>
                        <p class="text-sm font-bold text-slate-800 dark:text-white">
                            {{ filtering ? 'Sin resultados' : supplier ? 'Sin pólizas del proveedor' : machinery ? 'Sin pólizas de maquinaria' : 'Sin unidades' }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-brand-gray">
                            {{
                                filtering
                                    ? 'Nada coincide con los filtros aplicados.'
                                    : supplier
                                      ? 'Aún no hay unidades con póliza del proveedor.'
                                      : machinery
                                        ? 'Aún no hay pólizas de maquinaria registradas.'
                                        : 'Aún no hay unidades registradas.'
                            }}
                        </p>
                        <button
                            v-if="filtering"
                            type="button"
                            class="mt-3 cursor-pointer text-xs font-semibold text-brand hover:underline dark:text-white"
                            @click="clearFilters"
                        >
                            Limpiar filtros
                        </button>
                    </div>
                </div>

                <!-- Pie: rango y paginación -->
                <div
                    v-if="rows.data.length"
                    class="flex shrink-0 flex-wrap items-center justify-between gap-x-4 gap-y-2 border-t border-slate-100 px-3 py-2 text-xs text-slate-500 tall:py-2.5 @2xl:px-4 @6xl:px-6 dark:border-white/[0.06] dark:text-brand-gray"
                >
                    <p>
                        Mostrando
                        <span class="font-semibold text-slate-800 tabular-nums dark:text-white">{{ range.from }}–{{ range.to }}</span>
                        de
                        <span class="font-semibold text-slate-800 tabular-nums dark:text-white">{{ range.total }}</span>
                    </p>

                    <nav v-if="rows.meta.last_page > 1" aria-label="Paginación" class="flex items-center gap-1">
                        <button
                            type="button"
                            :class="[PAGE_BTN, 'hover:bg-slate-100 hover:text-brand dark:hover:bg-white/[0.06] dark:hover:text-white']"
                            :disabled="rows.meta.current_page <= 1"
                            aria-label="Página anterior"
                            @click="goTo(rows.meta.current_page - 1)"
                        >
                            <ChevronLeft class="size-3.5" />
                        </button>

                        <template v-for="(page, i) in pages" :key="`${page}-${i}`">
                            <span v-if="page === '…'" class="px-1 text-slate-400">…</span>
                            <button
                                v-else
                                type="button"
                                :class="[
                                    PAGE_BTN,
                                    page === rows.meta.current_page
                                        ? 'bg-brand text-white shadow-md shadow-brand/25 dark:bg-brand-light'
                                        : 'hover:bg-slate-100 hover:text-brand dark:hover:bg-white/[0.06] dark:hover:text-white',
                                ]"
                                :aria-current="page === rows.meta.current_page ? 'page' : undefined"
                                @click="goTo(page)"
                            >
                                {{ page }}
                            </button>
                        </template>

                        <button
                            type="button"
                            :class="[PAGE_BTN, 'hover:bg-slate-100 hover:text-brand dark:hover:bg-white/[0.06] dark:hover:text-white']"
                            :disabled="rows.meta.current_page >= rows.meta.last_page"
                            aria-label="Página siguiente"
                            @click="goTo(rows.meta.current_page + 1)"
                        >
                            <ChevronRight class="size-3.5" />
                        </button>
                    </nav>
                </div>
            </div>
        </div>

        <ConfirmDeleteDialog
            v-model:open="deleteOpen"
            :text="deleteText"
            @confirm="destroy"
        />
    </AppShell>
</template>
