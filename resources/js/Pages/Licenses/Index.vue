<script setup>
import { Head, router } from '@inertiajs/vue3';
import { BellRing, Building2, CalendarDays, CalendarRange, ChevronLeft, ChevronRight, Eye, FileBadge, Landmark, ListFilter, Pencil, Plus, Search, Trash2, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppShell from '@/Layouts/AppShell.vue';
import ConfirmDeleteDialog from '@/components/app/ConfirmDeleteDialog.vue';
import FilterSelect from '@/components/app/FilterSelect.vue';
import LicenseFormDialog from '@/components/app/LicenseFormDialog.vue';
import LicenseReminderDialog from '@/components/app/LicenseReminderDialog.vue';
import LicenseShowDialog from '@/components/app/LicenseShowDialog.vue';
import { usePermissions } from '@/composables/usePermissions';
import { LICENSE_STATUS, daysLeft, licenseStatus } from '@/lib/licenses';

const props = defineProps({
    /** { data: [{ id, name, company, authority, valid_until, valid_time, status, created_by, notification }], meta } */
    licenses: { type: Object, required: true },
    /** { total, active, expiring, expired, in_progress } — de todo el catálogo, sin filtros. */
    stats: { type: Object, required: true },
    /** { search, status, company, authority, year, month } — lo que ya viene aplicado. */
    filters: { type: Object, default: () => ({}) },
    /** { companies: [], authorities: [], years: [] } — sale de lo capturado. */
    options: { type: Object, default: () => ({ companies: [], authorities: [], years: [] }) },
    /** Correos con acceso al calendario: a ellos les llega el aviso. */
    sharedWith: { type: Array, default: () => [] },
});

const breadcrumbs = [{ label: 'Inicio', href: '/calendario' }, { label: 'Licencias y Permisos' }];

const { can } = usePermissions();

const formOpen = ref(false);
const editing = ref(null);

const showOpen = ref(false);
const viewing = ref(null);

function create() {
    editing.value = null;
    formOpen.value = true;
}

function edit(license) {
    editing.value = license;
    formOpen.value = true;
}

function show(license) {
    viewing.value = license;
    showOpen.value = true;
}

const reminderOpen = ref(false);
const reminding = ref(null);

function remind(license) {
    reminding.value = license;
    reminderOpen.value = true;
}

/* ---------- Eliminar ---------- */

const deleteOpen = ref(false);
const toDelete = ref(null);
const deleting = ref(null);

function askDelete(license) {
    toDelete.value = license;
    deleteOpen.value = true;
}

function destroy() {
    const license = toDelete.value;

    deleting.value = license.id;

    router.delete(`/licencias/${license.id}`, {
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
const company = ref(props.filters.company ?? '');
const authority = ref(props.filters.authority ?? '');
const year = ref(props.filters.year ?? '');
const month = ref(props.filters.month ?? '');

function visit(params) {
    router.get(
        '/licencias',
        {
            ...(search.value ? { search: search.value } : {}),
            ...(status.value ? { status: status.value } : {}),
            ...(company.value ? { company: company.value } : {}),
            ...(authority.value ? { authority: authority.value } : {}),
            ...(year.value ? { year: year.value } : {}),
            ...(month.value ? { month: month.value } : {}),
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
watch([status, company, authority, year, month], () => visit({}));

const filtering = computed(() =>
    Boolean(search.value || status.value || company.value || authority.value || year.value || month.value),
);

function clearFilters() {
    search.value = '';
    status.value = '';
    company.value = '';
    authority.value = '';
    year.value = '';
    month.value = '';
}

/** Lo aplicado, en fichas: se ve de un vistazo y cada una se quita sola. */
const activeFilters = computed(() =>
    [
        { key: 'status', label: statusOptions.find((o) => o.value === status.value)?.label, clear: () => (status.value = '') },
        { key: 'company', label: company.value, clear: () => (company.value = '') },
        { key: 'authority', label: authority.value, clear: () => (authority.value = '') },
        { key: 'year', label: year.value, clear: () => (year.value = '') },
        { key: 'month', label: monthOptions.find((o) => o.value === month.value)?.label, clear: () => (month.value = '') },
    ].filter((chip) => chip.label),
);

/* ---------- Opciones de los filtros ---------- */

// El estado es el único con catálogo fijo; el resto sale de lo capturado.
const statusOptions = Object.entries(LICENSE_STATUS).map(([value, meta]) => ({ value, label: meta.label, dot: meta.dot }));

const toOptions = (values) => values.map((value) => ({ value, label: value }));

const companyOptions = computed(() => toOptions(props.options.companies));
const authorityOptions = computed(() => toOptions(props.options.authorities));
const yearOptions = computed(() => toOptions(props.options.years));

/**
 * Los doce, siempre: el año va aparte, así que se puede pedir «todos los
 * noviembres». Salen del propio navegador para no repetir sus nombres aquí.
 */
const monthOptions = Array.from({ length: 12 }, (_, i) => {
    const label = new Date(2000, i, 1).toLocaleDateString('es-MX', { month: 'long' });

    return {
        value: String(i + 1).padStart(2, '0'),
        label: label[0].toUpperCase() + label.slice(1),
    };
});

function goTo(page) {
    visit({ page });
}

const range = computed(() => {
    const { current_page: page, per_page: size, total } = props.licenses.meta;
    const from = total ? (page - 1) * size + 1 : 0;

    return { from, to: from ? from + props.licenses.data.length - 1 : 0, total };
});

/** 1 … 4 5 6 … 12: la actual, sus vecinas y los extremos. */
const pages = computed(() => {
    const { current_page: current, last_page: last } = props.licenses.meta;
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

/** «2026-09-15» → «15 sep 2026». Se arma en hora local: con new Date(iso) saldría un día antes. */
function shortDate(iso) {
    if (!iso) return '—';

    const [y, m, d] = iso.split('-').map(Number);

    return new Date(y, m - 1, d).toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric' });
}

/** «12 días», «Hoy», «Vencida hace 3 días». Corto: va en una columna angosta. */
function daysText(iso) {
    const days = daysLeft(iso);

    if (days === 0) return 'Hoy';
    if (days > 0) return days === 1 ? '1 día' : `${days} días`;

    return days === -1 ? 'Vencida hace 1 día' : `Vencida hace ${-days} días`;
}

/** Mismos cortes que el estatus: rojo vencida, ámbar a 30 días o menos. */
function daysTone(iso) {
    const days = daysLeft(iso);

    if (days < 0) return 'text-red-600 dark:text-red-400';
    if (days <= 30) return 'text-amber-600 dark:text-amber-400';

    return 'text-slate-700 dark:text-slate-200';
}

const STATUS = LICENSE_STATUS;

const statusOf = licenseStatus;

/* ---------- Resumen ---------- */

// Solo informan: son de todo el catálogo y no filtran la tabla.
const cards = computed(() => [
    {
        key: 'total',
        label: 'Total',
        value: props.stats.total,
        hint: props.stats.total === 1 ? 'registro' : 'registros',
        icon: FileBadge,
        tone: 'bg-brand/[0.07] text-brand ring-brand/15 dark:bg-white/10 dark:text-white dark:ring-white/10',
    },
    ...Object.entries(STATUS).map(([key, meta]) => {
        const value = props.stats[key] ?? 0;

        return {
            key,
            label: meta.plural,
            value,
            hint: value === 1 ? 'registro' : 'registros',
            icon: meta.icon,
            tone: meta.tone,
        };
    }),
]);

/* Mismas medidas que la tabla de permisos. */
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
    <Head title="Licencias y Permisos" />

    <AppShell :breadcrumbs="breadcrumbs">
        <div class="flex flex-col md:h-full md:min-h-0">
            <!-- Encabezado -->
            <div class="mb-3 flex shrink-0 flex-wrap items-center justify-between gap-3 tall:mb-4">
                <div class="flex items-center gap-3">
                    <span
                        class="grid size-9 place-content-center rounded-xl bg-gradient-to-br from-brand-light to-brand text-white shadow-md shadow-brand/25 ring-1 ring-white/10"
                    >
                        <FileBadge class="size-4" />
                    </span>
                    <div>
                        <p class="text-[0.6rem] font-bold uppercase tracking-[0.2em] text-slate-400 dark:text-brand-gray/80">Cumplimiento</p>
                        <h1 class="text-xl font-bold tracking-tight text-brand dark:text-white">Licencias y Permisos</h1>
                    </div>
                </div>

                <button
                    v-if="can('licencias.create')"
                    type="button"
                    class="inline-flex h-9 cursor-pointer items-center gap-2 rounded-xl bg-brand px-4 text-[0.8rem] font-semibold text-white shadow-md shadow-brand/25 transition-all duration-150 hover:bg-brand/90 hover:shadow-lg hover:shadow-brand/30 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/25 active:translate-y-px dark:bg-brand-light dark:shadow-black/30 dark:hover:bg-brand-light/90"
                    @click="create"
                >
                    <Plus class="size-4" />
                    Nuevo registro
                </button>
            </div>

            <!-- Resumen: de todo el catálogo, no cambia con los filtros de la tabla -->
            <section aria-label="Resumen" class="mb-3 grid shrink-0 grid-cols-2 gap-2.5 tall:mb-4 sm:grid-cols-3 lg:grid-cols-5 tall:gap-3">
                <article
                    v-for="card in cards"
                    :key="card.key"
                    class="flex items-center gap-3 rounded-2xl border border-slate-200/80 bg-white px-3.5 py-3 shadow-[0_1px_3px_rgb(2_29_73/0.04),0_8px_24px_-12px_rgb(2_29_73/0.08)] dark:border-white/[0.08] dark:bg-brand-deep dark:shadow-none"
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
                <!-- Barra: buscador y filtros en una sola fila; debajo, lo aplicado -->
                <div class="flex shrink-0 flex-col gap-2 px-3 py-2.5 tall:py-3 @2xl:px-4 @6xl:px-6">
                    <!--
                        Anchos por contenido: cada filtro mide lo que mide su texto más largo
                        («Todas las autoridades», «Cualquier año») para que nada salga cortado.
                        Cuando ya no caben, la fila se parte sola en vez de encoger los controles.
                    -->
                    <div class="flex flex-wrap items-center gap-2">
                        <label class="relative min-w-0 flex-1 @xl:max-w-xs">
                            <span class="sr-only">Buscar licencias y permisos</span>
                            <input
                                v-model="search"
                                type="search"
                                placeholder="Buscar por nombre, empresa o autoridad…"
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

                        <!-- Mismo control para los cinco: solo cambia por qué filtra -->
                        <FilterSelect
                            v-model="status"
                            :options="statusOptions"
                            placeholder="Todos los estados"
                            label="Filtrar por estado"
                            width="@xl:w-44"
                        />

                        <FilterSelect
                            v-model="company"
                            :options="companyOptions"
                            :icon="Building2"
                            placeholder="Todas las empresas"
                            label="Filtrar por empresa"
                            width="@xl:w-48"
                        />

                        <FilterSelect
                            v-model="authority"
                            :options="authorityOptions"
                            :icon="Landmark"
                            placeholder="Todas las autoridades"
                            label="Filtrar por autoridad"
                            width="@xl:w-52"
                        />

                        <FilterSelect
                            v-model="year"
                            :options="yearOptions"
                            :icon="CalendarRange"
                            placeholder="Cualquier año"
                            label="Filtrar por año de vencimiento"
                            width="@xl:w-44"
                        />

                        <FilterSelect
                            v-model="month"
                            :options="monthOptions"
                            :icon="CalendarDays"
                            placeholder="Cualquier mes"
                            label="Filtrar por mes de vencimiento"
                            width="@xl:w-44"
                        />

                        <p class="ml-auto shrink-0 text-xs text-slate-500 dark:text-brand-gray">
                            <span class="font-bold text-slate-800 dark:text-white">{{ range.total }}</span>
                            {{ range.total === 1 ? 'registro' : 'registros' }}
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

                <!-- Sin scroll horizontal: las columnas que no caben se esconden y su dato baja debajo del nombre -->
                <div ref="tableBox" class="overflow-hidden border-t border-slate-100 md:min-h-0 md:flex-1 md:overflow-y-auto dark:border-white/[0.06]">
                    <table class="w-full text-[0.8rem]">
                        <thead class="sticky top-0 z-10 bg-slate-50 text-slate-500 dark:bg-brand-deep dark:text-brand-gray">
                            <tr>
                                <th :class="TH">Nombre / trámite</th>
                                <th :class="[TH, 'hidden @3xl:table-cell']">Empresa</th>
                                <th :class="[TH, 'hidden @4xl:table-cell']">Autoridad</th>
                                <th :class="[TH, 'hidden @lg:table-cell']">Vigencia</th>
                                <th :class="[TH, 'hidden @lg:table-cell']">Días restantes</th>
                                <th :class="[TH, 'hidden @2xl:table-cell']">Estatus</th>
                                <th :class="[TH, 'text-right']">Acciones</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100 dark:divide-white/[0.05]">
                            <tr
                                v-for="license in licenses.data"
                                :key="license.id"
                                class="transition-colors duration-150 hover:bg-slate-50/80 dark:hover:bg-white/[0.025]"
                                :class="deleting === license.id && 'pointer-events-none opacity-40'"
                                :style="rowHeight ? { height: `${rowHeight}px` } : null"
                            >
                                <td :class="[TD, 'w-full max-w-0']">
                                    <div class="flex items-center gap-2.5">
                                        <span
                                            class="grid size-7 shrink-0 place-content-center rounded-lg bg-slate-50 text-slate-500 ring-1 ring-inset ring-slate-500/15 dark:bg-white/[0.05] dark:text-brand-gray dark:ring-white/10"
                                        >
                                            <FileBadge class="size-3.5" />
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block truncate font-semibold text-slate-800 dark:text-white" :title="license.name">{{ license.name }}</span>
                                            <!-- En cards angostos los datos de las columnas ocultas bajan aquí -->
                                            <span class="block truncate text-[0.7rem] text-slate-400 @4xl:hidden dark:text-brand-gray/80">
                                                <span class="@3xl:hidden">{{ license.company ?? 'Sin empresa' }}</span>
                                                <span v-if="license.authority"><span class="@3xl:hidden"> · </span>{{ license.authority }}</span>
                                                <span class="@lg:hidden"> · vigencia {{ shortDate(license.valid_until) }}<template v-if="license.valid_until"> ({{ daysText(license.valid_until) }})</template></span>
                                                <span class="@2xl:hidden"> · {{ statusOf(license.status).label }}</span>
                                            </span>
                                        </span>
                                    </div>
                                </td>

                                <td :class="[TD, 'hidden @3xl:table-cell']">
                                    <span class="block max-w-[14rem] truncate text-slate-600 dark:text-slate-300" :title="license.company">{{ license.company ?? '—' }}</span>
                                </td>

                                <td :class="[TD, 'hidden @4xl:table-cell']">
                                    <span class="block max-w-[14rem] truncate text-slate-600 dark:text-slate-300" :title="license.authority">{{ license.authority ?? '—' }}</span>
                                </td>

                                <td :class="[TD, 'hidden whitespace-nowrap text-slate-600 tabular-nums @lg:table-cell dark:text-slate-300']">
                                    {{ license.valid_until ? shortDate(license.valid_until) : 'Por definir' }}
                                    <span v-if="license.valid_time" class="text-slate-400 dark:text-brand-gray/80">{{ license.valid_time }}</span>
                                </td>

                                <td :class="[TD, 'hidden whitespace-nowrap tabular-nums @lg:table-cell']">
                                    <span v-if="license.valid_until" class="font-semibold" :class="daysTone(license.valid_until)">
                                        {{ daysText(license.valid_until) }}
                                    </span>
                                    <span v-else class="text-slate-400 dark:text-brand-gray/70">—</span>
                                </td>

                                <td :class="[TD, 'hidden @2xl:table-cell']">
                                    <span
                                        class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[0.7rem] font-bold whitespace-nowrap ring-1 ring-inset"
                                        :class="statusOf(license.status).tone"
                                    >
                                        {{ statusOf(license.status).label }}
                                    </span>
                                </td>

                                <!-- Acciones aún sin funcionalidad -->
                                <td :class="TD">
                                    <div class="flex items-center justify-end gap-0.5 @2xl:gap-1">
                                        <button type="button" :class="[ACTION, NEUTRAL]" :aria-label="`Ver ${license.name}`" title="Ver detalle" @click="show(license)">
                                            <Eye class="size-4" />
                                        </button>
                                        <button
                                            v-if="can('licencias.update')"
                                            type="button"
                                            :class="[ACTION, NEUTRAL]"
                                            :aria-label="`Editar ${license.name}`"
                                            title="Editar"
                                            @click="edit(license)"
                                        >
                                            <Pencil class="size-4" />
                                        </button>
                                        <!-- Con recordatorio activo la campana va en color y con punto: se ve sin abrirla -->
                                        <button
                                            v-if="can('licencias.update')"
                                            type="button"
                                            :class="[
                                                ACTION,
                                                'relative',
                                                license.notification
                                                    ? 'bg-amber-50 text-amber-600 hover:bg-amber-100 focus-visible:ring-amber-500/25 dark:bg-amber-400/10 dark:text-amber-300 dark:hover:bg-amber-400/20'
                                                    : NEUTRAL,
                                            ]"
                                            :aria-label="`Aviso de ${license.name}${license.notification ? ' (activo)' : ''}`"
                                            :title="license.notification ? 'Aviso activo' : 'Configurar aviso'"
                                            @click="remind(license)"
                                        >
                                            <BellRing class="size-4" />
                                            <span
                                                v-if="license.notification"
                                                class="absolute top-1 right-1 size-1.5 rounded-full bg-amber-500 ring-2 ring-white dark:ring-brand-deep"
                                                aria-hidden="true"
                                            />
                                        </button>
                                        <button
                                            v-if="can('licencias.delete')"
                                            type="button"
                                            :class="[ACTION, 'text-slate-500 hover:bg-red-50 hover:text-red-600 focus-visible:ring-red-500/25 dark:text-brand-gray dark:hover:bg-red-500/10 dark:hover:text-red-300']"
                                            :aria-label="`Eliminar ${license.name}`"
                                            title="Eliminar"
                                            :disabled="deleting === license.id"
                                            @click="askDelete(license)"
                                        >
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!licenses.data.length">
                                <td colspan="7" class="px-6 py-8 text-center tall:py-14">
                                    <span
                                        class="mx-auto mb-2.5 grid size-10 place-content-center rounded-xl bg-slate-100 text-slate-400 dark:bg-white/[0.05] dark:text-brand-gray"
                                    >
                                        <component :is="filtering ? Search : FileBadge" class="size-5" />
                                    </span>
                                    <p class="text-sm font-bold text-slate-800 dark:text-white">{{ filtering ? 'Sin resultados' : 'Sin registros' }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-brand-gray">
                                        {{ filtering ? 'Nada coincide con los filtros aplicados.' : 'Aún no hay licencias ni permisos registrados.' }}
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
                    v-if="licenses.data.length"
                    class="flex shrink-0 flex-wrap items-center justify-between gap-x-4 gap-y-2 border-t border-slate-100 px-3 py-2 text-xs text-slate-500 tall:py-2.5 @2xl:px-4 @6xl:px-6 dark:border-white/[0.06] dark:text-brand-gray"
                >
                    <p>
                        Mostrando
                        <span class="font-semibold text-slate-800 tabular-nums dark:text-white">{{ range.from }}–{{ range.to }}</span>
                        de
                        <span class="font-semibold text-slate-800 tabular-nums dark:text-white">{{ range.total }}</span>
                    </p>

                    <nav v-if="licenses.meta.last_page > 1" aria-label="Paginación" class="flex items-center gap-1">
                        <button
                            type="button"
                            :class="[PAGE_BTN, 'hover:bg-slate-100 hover:text-brand dark:hover:bg-white/[0.06] dark:hover:text-white']"
                            :disabled="licenses.meta.current_page <= 1"
                            aria-label="Página anterior"
                            @click="goTo(licenses.meta.current_page - 1)"
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
                                    page === licenses.meta.current_page
                                        ? 'bg-brand text-white shadow-md shadow-brand/25 dark:bg-brand-light'
                                        : 'hover:bg-slate-100 hover:text-brand dark:hover:bg-white/[0.06] dark:hover:text-white',
                                ]"
                                :aria-current="page === licenses.meta.current_page ? 'page' : undefined"
                                @click="goTo(page)"
                            >
                                {{ page }}
                            </button>
                        </template>

                        <button
                            type="button"
                            :class="[PAGE_BTN, 'hover:bg-slate-100 hover:text-brand dark:hover:bg-white/[0.06] dark:hover:text-white']"
                            :disabled="licenses.meta.current_page >= licenses.meta.last_page"
                            aria-label="Página siguiente"
                            @click="goTo(licenses.meta.current_page + 1)"
                        >
                            <ChevronRight class="size-3.5" />
                        </button>
                    </nav>
                </div>
            </div>
        </div>

        <LicenseFormDialog v-model:open="formOpen" :license="editing" />

        <LicenseShowDialog v-model:open="showOpen" :license="viewing" />

        <LicenseReminderDialog v-model:open="reminderOpen" :license="reminding" :shared-with="sharedWith" />

        <ConfirmDeleteDialog
            v-model:open="deleteOpen"
            :text="`¿Seguro que quieres eliminar «${toDelete?.name ?? ''}»?`"
            @confirm="destroy"
        />
    </AppShell>
</template>
