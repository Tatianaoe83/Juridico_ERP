<script setup>
import { Head, router } from '@inertiajs/vue3';
import { BellRing, Check, ChevronDown, ChevronLeft, ChevronRight, Eye, FileBadge, Pencil, Plus, Search, Trash2, X } from 'lucide-vue-next';
import {
    SelectContent,
    SelectIcon,
    SelectItem,
    SelectItemIndicator,
    SelectItemText,
    SelectPortal,
    SelectRoot,
    SelectTrigger,
    SelectViewport,
} from 'reka-ui';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppShell from '@/Layouts/AppShell.vue';
import LicenseFormDialog from '@/components/app/LicenseFormDialog.vue';
import { usePermissions } from '@/composables/usePermissions';
import { LICENSE_STATUS, licenseStatus } from '@/lib/licenses';

const props = defineProps({
    /** { data: [{ id, name, company, authority, valid_until, status }], meta } */
    licenses: { type: Object, required: true },
    /** { total, active, expiring, expired, in_progress } — de todo el catálogo, sin filtros. */
    stats: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const breadcrumbs = [{ label: 'Inicio', href: '/calendario' }, { label: 'Licencias y permisos' }];

const { can } = usePermissions();

const formOpen = ref(false);

/* ---------- Búsqueda, estado y páginas ---------- */

// El servidor pagina de 10 en 10, igual que las demás tablas.
const PER_PAGE = 10;

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');

function visit(params) {
    router.get(
        '/licencias',
        {
            ...(search.value ? { search: search.value } : {}),
            ...(status.value ? { status: status.value } : {}),
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

// El estado se elige de una vez: va directo. Cambiarlo vuelve a la página 1.
watch(status, () => visit({}));

/** El Select de reka-ui no acepta '' como valor de opción: «todos» viaja como 'all'. */
const statusModel = computed({
    get: () => status.value || 'all',
    set: (value) => (status.value = value === 'all' ? '' : value),
});

const filtering = computed(() => Boolean(search.value || status.value));

function clearFilters() {
    search.value = '';
    status.value = '';
}

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

const STATUS = LICENSE_STATUS;

const statusOf = licenseStatus;

const statusOptions = [
    { key: 'all', label: 'Todos los estados', dot: 'bg-slate-300 dark:bg-white/25' },
    ...Object.entries(STATUS).map(([key, meta]) => ({ key, label: meta.label, dot: meta.dot })),
];

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
            hint: `${props.stats.total ? Math.round((value / props.stats.total) * 100) : 0}% del total`,
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
    <Head title="Licencias y permisos" />

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
                        <h1 class="text-xl font-bold tracking-tight text-brand dark:text-white">Licencias y permisos</h1>
                    </div>
                </div>

                <button
                    v-if="can('licencias.create')"
                    type="button"
                    class="inline-flex h-9 cursor-pointer items-center gap-2 rounded-xl bg-brand px-4 text-[0.8rem] font-semibold text-white shadow-md shadow-brand/25 transition-all duration-150 hover:bg-brand/90 hover:shadow-lg hover:shadow-brand/30 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/25 active:translate-y-px dark:bg-brand-light dark:shadow-black/30 dark:hover:bg-brand-light/90"
                    @click="formOpen = true"
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
                <!-- Barra: búsqueda, estado y conteo -->
                <div class="flex shrink-0 flex-wrap items-center justify-between gap-x-4 gap-y-2 px-3 py-2.5 tall:py-3 @2xl:px-4 @6xl:px-6">
                    <div class="flex w-full flex-wrap items-center gap-2 @xl:w-auto">
                        <label class="relative w-full @xl:w-80">
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

                        <!-- Estado: lista propia en vez del <select> nativo, con el color de cada uno -->
                        <SelectRoot v-model="statusModel">
                            <SelectTrigger
                                aria-label="Filtrar por estado"
                                class="group flex h-9 w-full cursor-pointer items-center gap-2 rounded-lg border px-3 text-[0.8rem] outline-none transition-[border-color,background-color,box-shadow] duration-150 focus-visible:ring-4 focus-visible:ring-brand/10 data-[state=open]:border-brand/50 data-[state=open]:bg-white data-[state=open]:ring-4 data-[state=open]:ring-brand/10 @xl:w-48 dark:focus-visible:ring-white/10 dark:data-[state=open]:border-brand-gray/50 dark:data-[state=open]:bg-white/[0.06] dark:data-[state=open]:ring-white/10"
                                :class="
                                    status
                                        ? 'border-brand/40 bg-brand/[0.04] text-slate-900 dark:border-brand-gray/40 dark:bg-white/[0.06] dark:text-white'
                                        : 'border-slate-200 bg-slate-50/70 text-slate-500 hover:border-slate-300 dark:border-white/10 dark:bg-white/[0.04] dark:text-white/60 dark:hover:border-white/20'
                                "
                            >
                                <span class="size-2 shrink-0 rounded-full" :class="status ? STATUS[status].dot : 'bg-slate-300 dark:bg-white/25'" />
                                <span class="flex-1 truncate text-left" :class="status && 'font-semibold'">
                                    {{ status ? STATUS[status].label : 'Todos los estados' }}
                                </span>
                                <SelectIcon as-child>
                                    <ChevronDown
                                        class="size-3.5 shrink-0 text-slate-400 transition-transform duration-200 group-data-[state=open]:rotate-180 dark:text-white/35"
                                    />
                                </SelectIcon>
                            </SelectTrigger>

                            <SelectPortal>
                                <SelectContent
                                    position="popper"
                                    :side-offset="6"
                                    class="z-50 max-h-72 min-w-[var(--reka-select-trigger-width)] overflow-hidden rounded-xl border border-slate-200/80 bg-white p-1 font-corporate shadow-xl shadow-brand-deep/10 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:slide-in-from-top-1 dark:border-white/10 dark:bg-brand-panel dark:shadow-black/40"
                                >
                                    <SelectViewport>
                                        <SelectItem
                                            v-for="option in statusOptions"
                                            :key="option.key"
                                            :value="option.key"
                                            class="flex cursor-pointer items-center gap-2.5 rounded-lg py-2 pr-2 pl-2.5 text-[0.8rem] text-slate-700 outline-none select-none data-[highlighted]:bg-slate-100 data-[highlighted]:text-slate-900 data-[state=checked]:font-semibold data-[state=checked]:text-brand dark:text-slate-200 dark:data-[highlighted]:bg-white/[0.07] dark:data-[highlighted]:text-white dark:data-[state=checked]:text-white"
                                        >
                                            <span class="size-2 shrink-0 rounded-full" :class="option.dot" />
                                            <SelectItemText class="flex-1">{{ option.label }}</SelectItemText>
                                            <SelectItemIndicator>
                                                <Check class="size-3.5" stroke-width="2.5" />
                                            </SelectItemIndicator>
                                        </SelectItem>
                                    </SelectViewport>
                                </SelectContent>
                            </SelectPortal>
                        </SelectRoot>
                    </div>

                    <p class="text-xs text-slate-500 dark:text-brand-gray">
                        <span class="font-bold text-slate-800 dark:text-white">{{ range.total }}</span>
                        {{ range.total === 1 ? 'registro' : 'registros' }}
                    </p>
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
                                <th :class="[TH, 'hidden @2xl:table-cell']">Estatus</th>
                                <th :class="[TH, 'text-right']">Acciones</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100 dark:divide-white/[0.05]">
                            <tr
                                v-for="license in licenses.data"
                                :key="license.id"
                                class="transition-colors duration-150 hover:bg-slate-50/80 dark:hover:bg-white/[0.025]"
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
                                                <span class="@lg:hidden"> · vigencia {{ shortDate(license.valid_until) }}</span>
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
                                        <button type="button" :class="[ACTION, NEUTRAL]" :aria-label="`Ver ${license.name}`" title="Ver detalle">
                                            <Eye class="size-4" />
                                        </button>
                                        <button type="button" :class="[ACTION, NEUTRAL]" :aria-label="`Editar ${license.name}`" title="Editar">
                                            <Pencil class="size-4" />
                                        </button>
                                        <button type="button" :class="[ACTION, NEUTRAL]" :aria-label="`Recordatorio de ${license.name}`" title="Recordatorio">
                                            <BellRing class="size-4" />
                                        </button>
                                        <button
                                            type="button"
                                            :class="[ACTION, 'text-slate-500 hover:bg-red-50 hover:text-red-600 focus-visible:ring-red-500/25 dark:text-brand-gray dark:hover:bg-red-500/10 dark:hover:text-red-300']"
                                            :aria-label="`Eliminar ${license.name}`"
                                            title="Eliminar"
                                        >
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!licenses.data.length">
                                <td colspan="6" class="px-6 py-8 text-center tall:py-14">
                                    <span
                                        class="mx-auto mb-2.5 grid size-10 place-content-center rounded-xl bg-slate-100 text-slate-400 dark:bg-white/[0.05] dark:text-brand-gray"
                                    >
                                        <component :is="filtering ? Search : FileBadge" class="size-5" />
                                    </span>
                                    <p class="text-sm font-bold text-slate-800 dark:text-white">{{ filtering ? 'Sin resultados' : 'Sin registros' }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-brand-gray">
                                        {{ filtering ? 'Nada coincide con la búsqueda o el estado elegido.' : 'Aún no hay licencias ni permisos registrados.' }}
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

        <LicenseFormDialog v-model:open="formOpen" />
    </AppShell>
</template>
