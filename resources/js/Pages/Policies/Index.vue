<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowRight,
    ChevronLeft,
    ChevronRight,
    Clock,
    Eye,
    FileCheck,
    Layers,
    Pencil,
    Plus,
    Search,
    ShieldAlert,
    ShieldCheck,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppShell from '@/Layouts/AppShell.vue';
import ConfirmDeleteDialog from '@/components/app/ConfirmDeleteDialog.vue';
import { usePermissions } from '@/composables/usePermissions';
import { BOND_CATEGORIES, COVERAGE_STATUS, POLICY_COVERAGES, coverageStatus } from '@/lib/coverages';
import { daysLeft, localDate, remainingLabel } from '@/lib/licenses';
import { money, shortDate } from '@/lib/units';

const props = defineProps({
    /** { data: [{ key, type, number, provider, subject, detail, valid_from, valid_until, amount, status }], meta } */
    coverages: { type: Object, required: true },
    /** { total, active, expiring, expired, warning_days } — de todo, sin filtros. */
    stats: { type: Object, required: true },
    /** { search, type } — lo que ya viene aplicado. */
    filters: { type: Object, default: () => ({}) },
});

const breadcrumbs = [{ label: 'Inicio', href: '/calendario' }, { label: 'Pólizas y Fianzas' }];

// Los botones de crear y editar solo se muestran a quien tiene el permiso.
const { can } = usePermissions();

/* ---------- Búsqueda, tipo y páginas ---------- */

// El servidor pagina de 10 en 10, igual que las demás tablas.
const PER_PAGE = 10;

const search = ref(props.filters.search ?? '');

// Vacío = pólizas y fianzas.
const type = ref(props.filters.type ?? '');

const TYPE_TABS = [
    { value: '', label: 'Todas' },
    { value: 'policy', label: 'Pólizas' },
    { value: 'bond', label: 'Fianzas' },
];

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
    { key: '', label: 'Todas', value: props.stats.total, hint: 'pólizas y fianzas', icon: Layers, tone: 'bg-brand/[0.07] text-brand ring-brand/15 dark:bg-white/10 dark:text-white dark:ring-white/15' },
    { key: 'active', label: 'Vigentes', value: props.stats.active, hint: 'sin riesgo inmediato', icon: ShieldCheck, tone: COVERAGE_STATUS.active.tone },
    { key: 'expiring', label: 'Por vencer', value: props.stats.expiring, hint: `en ${props.stats.warning_days} días o menos`, icon: Clock, tone: COVERAGE_STATUS.expiring.tone },
    { key: 'expired', label: 'Vencidas', value: props.stats.expired, hint: 'requieren atención', icon: ShieldAlert, tone: COVERAGE_STATUS.expired.tone },
]);

/* ---------- Presentación ---------- */

/** Cada tipo con su icono y su color: se distinguen sin leer. */
const TYPE_META = {
    policy: {
        label: 'Póliza de obra',
        icon: FileCheck,
        tile: 'bg-brand/[0.07] text-brand ring-brand/15 dark:bg-white/10 dark:text-white dark:ring-white/15',
    },
    bond: {
        label: 'Fianza',
        icon: ShieldCheck,
        tile: 'bg-violet-50 text-violet-700 ring-violet-600/15 dark:bg-violet-400/10 dark:text-violet-300 dark:ring-violet-400/20',
    },
};

/** La categoría: en pólizas, su cobertura; en fianzas, su categoría. */
function category(row) {
    if (row.type === 'policy') return POLICY_COVERAGES[row.coverage] ?? 'Sin cobertura';

    return BOND_CATEGORIES[row.category] ?? 'Sin categoría';
}

/** El color de la barra y del texto de lo que falta, según cómo va la vigencia. */
const VALIDITY_TONE = {
    active: { bar: 'bg-emerald-500', text: 'text-emerald-700 dark:text-emerald-300' },
    expiring: { bar: 'bg-amber-500', text: 'text-amber-700 dark:text-amber-300' },
    expired: { bar: 'bg-red-500', text: 'text-red-600 dark:text-red-400' },
    cancelling: { bar: 'bg-orange-500', text: 'text-orange-700 dark:text-orange-300' },
    cancelled: { bar: 'bg-slate-300 dark:bg-white/20', text: 'text-slate-500 dark:text-brand-gray' },
    unknown: { bar: 'bg-slate-300 dark:bg-white/20', text: 'text-slate-500 dark:text-brand-gray' },
};

/**
 * La vigencia de una fila: sus fechas, cuánto lleva transcurrido (0 a 100) y
 * cuánto le falta. Sin inicio no hay barra; sin fin no hay nada que medir.
 */
function validity(row) {
    const tone = VALIDITY_TONE[row.status] ?? VALIDITY_TONE.unknown;

    if (!row.valid_until) {
        return { from: shortDate(row.valid_from), to: null, progress: null, remaining: 'Sin fin de vigencia', tone };
    }

    let progress = null;

    if (row.valid_from) {
        const total = localDate(row.valid_until) - localDate(row.valid_from);
        const left = daysLeft(row.valid_until) * 86_400_000;

        progress = total > 0 ? Math.min(100, Math.max(0, Math.round(((total - left) / total) * 100))) : 100;
    }

    return {
        from: shortDate(row.valid_from),
        to: shortDate(row.valid_until),
        progress,
        remaining: row.status === 'cancelled' ? 'Cancelada' : remainingLabel(row.valid_until),
        tone,
    };
}

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

    // La fila se apaga mientras el servidor borra.
    deleting.value = row.key;

    router.delete(showUrl(row), {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
}

/** La póliza se lleva sus documentos y endosos: se avisa antes. */
const deleteText = computed(() => {
    const row = toDelete.value;

    if (!row) return '';

    return row.type === 'policy'
        ? `¿Seguro que quieres eliminar la póliza «${row.number}»? También se borran sus documentos y endosos.`
        : `¿Seguro que quieres eliminar la fianza «${row.number}»?`;
});

const showUrl = (row) => (row.type === 'bond' ? `/polizas/fianzas/${row.id}` : `/polizas/${row.id}`);

const TH = 'px-3 py-2 text-left text-[0.62rem] font-bold uppercase tracking-[0.14em] whitespace-nowrap tall:py-2.5 @2xl:px-4 @6xl:px-6';
const TD = 'px-3 py-2 @2xl:px-4 @6xl:px-6';

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

                <!-- Uno solo: en el alta se elige si es póliza o fianza -->
                <Link
                    v-if="can('polizas.create')"
                    :href="type === 'bond' ? '/polizas/crear?tipo=fianza' : '/polizas/crear'"
                    class="inline-flex h-9 items-center gap-2 rounded-xl bg-brand px-4 text-[0.8rem] font-semibold text-white shadow-md shadow-brand/25 transition-all duration-150 hover:bg-brand/90 hover:shadow-lg hover:shadow-brand/30 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/25 active:translate-y-px dark:bg-brand-light dark:shadow-black/30 dark:hover:bg-brand-light/90"
                >
                    <Plus class="size-4" />
                    Nuevo registro
                </Link>
            </div>

            <!-- Resumen: de todas las coberturas, no cambia con los filtros de la tabla -->
            <section aria-label="Resumen por estado" class="mb-3 grid shrink-0 grid-cols-2 gap-2.5 tall:mb-4 lg:grid-cols-4 tall:gap-3">
                <article
                    v-for="card in cards"
                    :key="card.key || 'all'"
                    class="flex items-center gap-3 rounded-2xl border border-slate-200/80 bg-white px-3.5 py-3 shadow-[0_1px_3px_rgb(2_29_73/0.04),0_8px_24px_-12px_rgb(2_29_73/0.08)] dark:border-white/[0.08] dark:bg-brand-deep dark:shadow-none"
                >
                    <span class="grid size-10 shrink-0 place-content-center rounded-xl ring-1 ring-inset" :class="card.tone">
                        <component :is="card.icon" class="size-[1.1rem]" />
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-[0.62rem] font-bold uppercase tracking-[0.14em] text-slate-400 dark:text-brand-gray/80">{{ card.label }}</span>
                        <span class="block text-2xl leading-tight font-bold tabular-nums text-slate-800 dark:text-white">{{ card.value }}</span>
                        <span class="block truncate text-[0.68rem] text-slate-400 dark:text-brand-gray/70">{{ card.hint }}</span>
                    </span>
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
                                placeholder="Buscar por número, aseguradora, obra o beneficiario…"
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

                        <!-- Tipo: control segmentado, se ve qué está elegido sin abrir nada -->
                        <div
                            class="inline-flex h-9 shrink-0 items-center gap-0.5 self-start rounded-lg bg-slate-100 p-0.5 ring-1 ring-inset ring-slate-200/70 @xl:self-auto dark:bg-white/[0.05] dark:ring-white/[0.06]"
                            role="radiogroup"
                            aria-label="Filtrar por tipo"
                        >
                            <button
                                v-for="tab in TYPE_TABS"
                                :key="tab.value || 'all'"
                                type="button"
                                role="radio"
                                :aria-checked="type === tab.value"
                                class="h-8 cursor-pointer rounded-md px-3 text-[0.75rem] font-semibold transition-colors duration-150 focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-brand/25"
                                :class="
                                    type === tab.value
                                        ? 'bg-white text-brand shadow-sm ring-1 ring-slate-900/5 dark:bg-white/15 dark:text-white dark:ring-white/10'
                                        : 'text-slate-500 hover:text-slate-800 dark:text-brand-gray dark:hover:text-white'
                                "
                                @click="type = tab.value"
                            >
                                {{ tab.label }}
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 text-xs text-slate-500 dark:text-brand-gray">
                        <button
                            v-if="filtering"
                            type="button"
                            class="cursor-pointer font-semibold text-brand hover:underline dark:text-white"
                            @click="clearFilters"
                        >
                            Limpiar filtros
                        </button>
                        <p>
                            <span class="font-bold text-slate-800 dark:text-white">{{ coverages.meta.total }}</span>
                            {{ coverages.meta.total === 1 ? 'cobertura' : 'coberturas' }}
                        </p>
                    </div>
                </div>

                <div ref="tableBox" class="overflow-x-auto border-t border-slate-100 md:min-h-0 md:flex-1 md:overflow-y-auto dark:border-white/[0.06]">
                    <table class="w-full text-[0.8rem]">
                        <thead class="sticky top-0 z-10 bg-slate-50 text-slate-500 dark:bg-brand-deep dark:text-brand-gray">
                            <tr>
                                <th :class="TH">Póliza / Fianza</th>
                                <th :class="[TH, 'hidden @3xl:table-cell']">Aseguradora / Afianzadora</th>
                                <th :class="[TH, 'hidden @2xl:table-cell']">Vigencia</th>
                                <th :class="[TH, 'hidden text-right @xl:table-cell']">Monto</th>
                                <th :class="TH">Estado</th>
                                <th :class="[TH, 'text-right']">Acciones</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100 dark:divide-white/[0.05]">
                            <tr
                                v-for="row in coverages.data"
                                :key="row.key"
                                class="group transition-colors duration-150 hover:bg-slate-50/80 dark:hover:bg-white/[0.025]"
                                :class="deleting === row.key && 'pointer-events-none opacity-40'"
                                :style="rowHeight ? { height: `${rowHeight}px` } : null"
                            >
                                <!-- Qué es, su número y sobre qué: la obra o el beneficiario -->
                                <td :class="[TD, 'max-w-0 @3xl:w-[30%]']">
                                    <div class="flex items-center gap-2.5">
                                        <span
                                            class="grid size-8 shrink-0 place-content-center rounded-lg ring-1 ring-inset"
                                            :class="TYPE_META[row.type].tile"
                                            :title="TYPE_META[row.type].label"
                                        >
                                            <component :is="TYPE_META[row.type].icon" class="size-4" />
                                        </span>
                                        <div class="min-w-0">
                                            <p class="flex items-center gap-1.5">
                                                <Link
                                                    :href="showUrl(row)"
                                                    class="truncate font-semibold text-slate-800 hover:text-brand hover:underline dark:text-white"
                                                    :title="row.number"
                                                >
                                                    {{ row.number }}
                                                </Link>
                                                <span class="shrink-0 text-[0.58rem] font-bold uppercase tracking-wider text-slate-400 dark:text-brand-gray/70">
                                                    {{ TYPE_META[row.type].label }}
                                                </span>
                                            </p>
                                            <p class="truncate text-[0.72rem] text-slate-500 dark:text-brand-gray" :title="row.subject ?? undefined">
                                                {{ row.subject ?? '—' }}
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                <!-- Quién la da y qué cubre -->
                                <td :class="[TD, 'hidden max-w-0 @3xl:table-cell @3xl:w-[22%]']">
                                    <p
                                        class="truncate"
                                        :class="row.provider ? 'font-semibold text-slate-700 dark:text-slate-200' : 'text-slate-400 dark:text-brand-gray/70'"
                                    >
                                        {{ row.provider ?? 'Sin proveedor' }}
                                    </p>
                                    <p class="truncate text-[0.68rem] font-medium uppercase tracking-wide text-slate-400 dark:text-brand-gray/70">{{ category(row) }}</p>
                                </td>

                                <!-- Vigencia: fechas, barra de lo transcurrido y cuánto falta -->
                                <td :class="[TD, 'hidden @2xl:table-cell @2xl:w-56']">
                                    <template v-for="v in [validity(row)]" :key="row.key">
                                        <p class="flex items-center gap-1 text-[0.72rem] tabular-nums whitespace-nowrap text-slate-600 dark:text-slate-300">
                                            <span>{{ v.from ?? '—' }}</span>
                                            <ArrowRight class="size-3 shrink-0 text-slate-300 dark:text-white/25" />
                                            <span class="font-semibold text-slate-800 dark:text-white">{{ v.to ?? '—' }}</span>
                                        </p>
                                        <div
                                            v-if="v.progress !== null"
                                            class="mt-1 h-1 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-white/[0.08]"
                                            role="progressbar"
                                            :aria-valuenow="v.progress"
                                            aria-valuemin="0"
                                            aria-valuemax="100"
                                            :aria-label="`Vigencia transcurrida: ${v.progress}%`"
                                        >
                                            <div class="h-full rounded-full transition-[width] duration-300" :class="v.tone.bar" :style="{ width: `${v.progress}%` }" />
                                        </div>
                                        <p class="mt-0.5 text-[0.66rem] font-semibold" :class="v.tone.text">{{ v.remaining }}</p>
                                    </template>
                                </td>

                                <!-- Monto: lo que se paga en pólizas (IVA incluido), lo afianzado en fianzas -->
                                <td :class="[TD, 'hidden text-right whitespace-nowrap @xl:table-cell']">
                                    <span v-if="row.amount !== null" class="font-semibold tabular-nums text-slate-800 dark:text-white">{{ money(row.amount) }}</span>
                                    <span v-else class="text-slate-400 dark:text-brand-gray/70">—</span>
                                </td>

                                <!-- Estado de la vigencia -->
                                <td :class="[TD, 'whitespace-nowrap']">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[0.65rem] font-bold ring-1 ring-inset"
                                        :class="coverageStatus(row.status).tone"
                                    >
                                        <component :is="coverageStatus(row.status).icon" class="size-3" />
                                        {{ coverageStatus(row.status).label }}
                                    </span>
                                </td>

                                <!-- Acciones -->
                                <td :class="TD">
                                    <div class="flex items-center justify-end gap-0.5 @2xl:gap-1">
                                        <Link :href="showUrl(row)" :class="[ACTION, NEUTRAL]" :aria-label="`Ver ${row.number}`" title="Ver detalle">
                                            <Eye class="size-4" />
                                        </Link>
                                        <Link
                                            v-if="can('polizas.update')"
                                            :href="`${showUrl(row)}/editar`"
                                            :class="[ACTION, NEUTRAL]"
                                            :aria-label="`Editar ${row.number}`"
                                            title="Editar"
                                        >
                                            <Pencil class="size-4" />
                                        </Link>
                                        <button
                                            v-if="can('polizas.delete')"
                                            type="button"
                                            :class="[ACTION, 'text-slate-500 hover:bg-red-50 hover:text-red-600 focus-visible:ring-red-500/25 dark:text-brand-gray dark:hover:bg-red-500/10 dark:hover:text-red-300']"
                                            :aria-label="`Eliminar ${row.number}`"
                                            title="Eliminar"
                                            :disabled="deleting === row.key"
                                            @click="askDelete(row)"
                                        >
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!coverages.data.length">
                                <td colspan="6" class="px-6 py-8 text-center tall:py-14">
                                    <span
                                        class="mx-auto mb-2.5 grid size-10 place-content-center rounded-xl bg-slate-100 text-slate-400 dark:bg-white/[0.05] dark:text-brand-gray"
                                    >
                                        <Search class="size-5" />
                                    </span>
                                    <p class="text-sm font-bold text-slate-800 dark:text-white">Sin resultados</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-brand-gray">
                                        {{ filtering ? 'Ninguna póliza o fianza coincide con los filtros.' : 'Aún no hay pólizas ni fianzas registradas.' }}
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

        <ConfirmDeleteDialog v-model:open="deleteOpen" :text="deleteText" @confirm="destroy" />
    </AppShell>
</template>
