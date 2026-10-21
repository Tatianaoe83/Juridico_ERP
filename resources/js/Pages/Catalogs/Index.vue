<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Pencil, Plus, Search, Star, Trash2, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppShell from '@/Layouts/AppShell.vue';
import CatalogFormDialog from '@/components/app/CatalogFormDialog.vue';
import ConfirmDeleteDialog from '@/components/app/ConfirmDeleteDialog.vue';
import { CATALOGS } from '@/lib/catalogs';

/** La misma pantalla para los cuatro catálogos: cambia el slug. */
const props = defineProps({
    /** 'unidades-de-negocio' | 'aseguradoras' | 'afianzadoras' | 'beneficiarios' */
    catalog: { type: String, required: true },
    /** [{ id, name, uses }] */
    items: { type: Array, required: true },
});

const meta = computed(() => CATALOGS[props.catalog]);

const breadcrumbs = computed(() => [{ label: 'Inicio', href: '/calendario' }, { label: meta.value.title }]);

/* ---------- Búsqueda y páginas ---------- */

// Son pocos y ya vienen todos: se filtra y pagina aquí, sin ir al servidor.
const PER_PAGE = 10;

const search = ref('');
const page = ref(1);

const filtered = computed(() => {
    const term = search.value.trim().toLowerCase();

    return term ? props.items.filter((item) => item.name.toLowerCase().includes(term)) : props.items;
});

const lastPage = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)));

const visible = computed(() => filtered.value.slice((page.value - 1) * PER_PAGE, page.value * PER_PAGE));

/* ---------- Que las 10 filas cubran el card ---------- */

/** Igual que en Permisos: en computadora cada fila mide el alto disponible entre 10. */
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

    rowHeight.value = Math.floor((box.clientHeight - head - PER_PAGE) / PER_PAGE);
}

onMounted(() => {
    observer = new ResizeObserver(measure);

    if (tableBox.value) observer.observe(tableBox.value);
});

onBeforeUnmount(() => observer?.disconnect());

// Buscar vuelve a la primera página; borrar el último de una página, a la anterior.
// Cambiar de catálogo reutiliza la página: la búsqueda no se arrastra.
watch(search, () => (page.value = 1));
watch(lastPage, (last) => page.value > last && (page.value = last));
watch(() => props.catalog, () => (search.value = ''));

const range = computed(() => {
    const from = filtered.value.length ? (page.value - 1) * PER_PAGE + 1 : 0;

    return { from, to: from ? from + visible.value.length - 1 : 0, total: filtered.value.length };
});

/** 1 … 4 5 6 … 12: la actual, sus vecinas y los extremos. */
const pages = computed(() => {
    const last = lastPage.value;
    const wanted = new Set([1, last, page.value - 1, page.value, page.value + 1].filter((n) => n >= 1 && n <= last));
    const sorted = [...wanted].sort((a, b) => a - b);

    return sorted.flatMap((n, i) => (i && n - sorted[i - 1] > 1 ? ['…', n] : [n]));
});

/* ---------- Alta y edición ---------- */

const formOpen = ref(false);
const editing = ref(null);

function create() {
    editing.value = null;
    formOpen.value = true;
}

function edit(item) {
    editing.value = item;
    formOpen.value = true;
}

/* ---------- Eliminar ---------- */

const deleteOpen = ref(false);
const toDelete = ref(null);
const deleting = ref(null);

function askDelete(item) {
    toDelete.value = item;
    deleteOpen.value = true;
}

function destroy() {
    const item = toDelete.value;

    deleting.value = item.id;

    router.delete(`/catalogos/${props.catalog}/${item.id}`, {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
}

/* ---------- Aseguradora preferida ---------- */

// La que ganó la subasta: sale por defecto en los automóviles empresariales.
const insurers = computed(() => props.catalog === 'aseguradoras');

const preferring = ref(null);

function prefer(item) {
    preferring.value = item.id;

    router.patch(`/catalogos/aseguradoras/${item.id}/preferida`, {}, {
        preserveScroll: true,
        onFinish: () => (preferring.value = null),
    });
}

const deleteText = computed(() => (toDelete.value ? `¿Seguro que quieres eliminar «${toDelete.value.name}»?` : ''));

const uses = (count) => (count === 1 ? '1 registro' : `${count} registros`);

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
    <Head :title="meta.title" />

    <AppShell :breadcrumbs="breadcrumbs">
        <div class="flex flex-col md:h-full md:min-h-0">
            <!-- Encabezado -->
            <div class="mb-3 flex shrink-0 flex-wrap items-center justify-between gap-3 tall:mb-4">
                <div class="flex items-center gap-3">
                    <span
                        class="grid size-9 place-content-center rounded-xl bg-gradient-to-br from-brand-light to-brand text-white shadow-md shadow-brand/25 ring-1 ring-white/10"
                    >
                        <component :is="meta.icon" class="size-4" />
                    </span>
                    <div>
                        <h1 class="text-xl font-bold tracking-tight text-brand dark:text-white">{{ meta.title }}</h1>
                    </div>
                </div>

                <button
                    type="button"
                    class="inline-flex h-9 cursor-pointer items-center gap-2 rounded-xl bg-brand px-4 text-[0.8rem] font-semibold text-white shadow-md shadow-brand/25 transition-all duration-150 hover:bg-brand/90 hover:shadow-lg hover:shadow-brand/30 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/25 active:translate-y-px dark:bg-brand-light dark:shadow-black/30 dark:hover:bg-brand-light/90"
                    @click="create"
                >
                    <Plus class="size-4" />
                    {{ meta.create }}
                </button>
            </div>

            <div
                class="@container flex flex-col overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_1px_3px_rgb(2_29_73/0.04),0_8px_24px_-12px_rgb(2_29_73/0.08)] md:min-h-0 md:flex-1 dark:border-white/[0.08] dark:bg-brand-deep dark:shadow-none"
            >
                <!-- Barra: búsqueda y conteo -->
                <div class="flex shrink-0 flex-wrap items-center justify-between gap-x-4 gap-y-2 px-3 py-2.5 tall:py-3 @2xl:px-4 @6xl:px-6">
                    <label class="relative w-full @xl:max-w-sm">
                        <span class="sr-only">Buscar en {{ meta.title.toLowerCase() }}</span>
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Buscar por nombre…"
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

                    <p class="text-xs text-slate-500 dark:text-brand-gray">
                        <span class="font-bold text-slate-800 dark:text-white">{{ items.length }}</span>
                        {{ items.length === 1 ? 'registrado' : 'registrados' }}
                    </p>
                </div>

                <div ref="tableBox" class="overflow-hidden border-t border-slate-100 md:min-h-0 md:flex-1 md:overflow-y-auto dark:border-white/[0.06]">
                    <table class="w-full text-[0.8rem]">
                        <thead class="sticky top-0 z-10 bg-slate-50 text-slate-500 dark:bg-brand-deep dark:text-brand-gray">
                            <tr>
                                <th :class="TH">Nombre</th>
                                <th :class="[TH, 'hidden @lg:table-cell']">En uso</th>
                                <th :class="[TH, 'text-right']">Acciones</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100 dark:divide-white/[0.05]">
                            <tr
                                v-for="item in visible"
                                :key="item.id"
                                class="transition-colors duration-150 hover:bg-slate-50/80 dark:hover:bg-white/[0.025]"
                                :class="deleting === item.id && 'pointer-events-none opacity-40'"
                                :style="rowHeight ? { height: `${rowHeight}px` } : null"
                            >
                                <td :class="[TD, 'w-full max-w-0']">
                                    <div class="flex items-center gap-2.5">
                                        <span
                                            class="grid size-7 shrink-0 place-content-center rounded-lg bg-slate-50 text-slate-500 ring-1 ring-inset ring-slate-500/15 dark:bg-white/[0.05] dark:text-brand-gray dark:ring-white/10"
                                        >
                                            <component :is="meta.icon" class="size-3.5" />
                                        </span>
                                        <span class="min-w-0">
                                            <span class="flex min-w-0 items-center gap-2">
                                                <span class="truncate font-semibold text-slate-800 dark:text-white" :title="item.name">{{ item.name }}</span>
                                                <Star
                                                    v-if="item.preferred"
                                                    class="size-3.5 shrink-0 fill-current text-amber-500 dark:text-amber-300"
                                                    role="img"
                                                    aria-label="Por defecto"
                                                />
                                            </span>
                                            <!-- En cards angostos el uso baja aquí -->
                                            <span class="block truncate text-[0.7rem] text-slate-400 @lg:hidden dark:text-brand-gray/80">
                                                {{ item.uses ? `En ${uses(item.uses)}` : 'Sin usar' }}
                                            </span>
                                        </span>
                                    </div>
                                </td>

                                <td :class="[TD, 'hidden whitespace-nowrap text-slate-600 tabular-nums @lg:table-cell dark:text-slate-300']">
                                    {{ item.uses ? uses(item.uses) : 'Sin usar' }}
                                </td>

                                <td :class="TD">
                                    <div class="flex items-center justify-end gap-0.5 @2xl:gap-1">
                                        <button
                                            v-if="insurers"
                                            type="button"
                                            :class="[ACTION, item.preferred ? 'text-amber-500 hover:bg-amber-50 focus-visible:ring-amber-500/25 dark:text-amber-300 dark:hover:bg-amber-400/10' : NEUTRAL]"
                                            :aria-label="item.preferred ? `Quitar ${item.name} como aseguradora por defecto` : `Usar ${item.name} por defecto en automóviles empresariales`"
                                            :aria-pressed="item.preferred"
                                            :title="item.preferred ? 'Quitar por defecto' : 'Usar por defecto en automóviles empresariales'"
                                            :disabled="preferring === item.id"
                                            @click="prefer(item)"
                                        >
                                            <Star class="size-4" :class="item.preferred && 'fill-current'" />
                                        </button>
                                        <button type="button" :class="[ACTION, NEUTRAL]" :aria-label="`Editar ${item.name}`" title="Editar" @click="edit(item)">
                                            <Pencil class="size-4" />
                                        </button>
                                        <!-- En uso no se borra: las pólizas y fianzas se quedarían sin el dato -->
                                        <button
                                            type="button"
                                            :class="[ACTION, 'text-slate-500 hover:bg-red-50 hover:text-red-600 focus-visible:ring-red-500/25 dark:text-brand-gray dark:hover:bg-red-500/10 dark:hover:text-red-300']"
                                            :aria-label="`Eliminar ${item.name}`"
                                            :title="item.uses ? `No se puede eliminar: está en ${uses(item.uses)}` : 'Eliminar'"
                                            :disabled="deleting === item.id || item.uses > 0"
                                            @click="askDelete(item)"
                                        >
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!visible.length">
                                <td colspan="3" class="px-6 py-8 text-center tall:py-14">
                                    <span
                                        class="mx-auto mb-2.5 grid size-10 place-content-center rounded-xl bg-slate-100 text-slate-400 dark:bg-white/[0.05] dark:text-brand-gray"
                                    >
                                        <component :is="search ? Search : meta.icon" class="size-5" />
                                    </span>
                                    <p class="text-sm font-bold text-slate-800 dark:text-white">{{ search ? 'Sin resultados' : `Sin ${meta.title.toLowerCase()}` }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-brand-gray">
                                        {{ search ? `Nada coincide con «${search}».` : 'Agrega el primero para poder elegirlo en los formularios.' }}
                                    </p>
                                    <button
                                        v-if="search"
                                        type="button"
                                        class="mt-3 cursor-pointer text-xs font-semibold text-brand hover:underline dark:text-white"
                                        @click="search = ''"
                                    >
                                        Limpiar búsqueda
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pie: rango y paginación -->
                <div
                    v-if="visible.length"
                    class="flex shrink-0 flex-wrap items-center justify-between gap-x-4 gap-y-2 border-t border-slate-100 px-3 py-2 text-xs text-slate-500 tall:py-2.5 @2xl:px-4 @6xl:px-6 dark:border-white/[0.06] dark:text-brand-gray"
                >
                    <p>
                        Mostrando
                        <span class="font-semibold text-slate-800 tabular-nums dark:text-white">{{ range.from }}–{{ range.to }}</span>
                        de
                        <span class="font-semibold text-slate-800 tabular-nums dark:text-white">{{ range.total }}</span>
                    </p>

                    <nav v-if="lastPage > 1" aria-label="Paginación" class="flex items-center gap-1">
                        <button
                            type="button"
                            :class="[PAGE_BTN, 'hover:bg-slate-100 hover:text-brand dark:hover:bg-white/[0.06] dark:hover:text-white']"
                            :disabled="page <= 1"
                            aria-label="Página anterior"
                            @click="page--"
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
                                    n === page
                                        ? 'bg-brand text-white shadow-md shadow-brand/25 dark:bg-brand-light'
                                        : 'hover:bg-slate-100 hover:text-brand dark:hover:bg-white/[0.06] dark:hover:text-white',
                                ]"
                                :aria-current="n === page ? 'page' : undefined"
                                @click="page = n"
                            >
                                {{ n }}
                            </button>
                        </template>

                        <button
                            type="button"
                            :class="[PAGE_BTN, 'hover:bg-slate-100 hover:text-brand dark:hover:bg-white/[0.06] dark:hover:text-white']"
                            :disabled="page >= lastPage"
                            aria-label="Página siguiente"
                            @click="page++"
                        >
                            <ChevronRight class="size-3.5" />
                        </button>
                    </nav>
                </div>
            </div>
        </div>

        <CatalogFormDialog v-model:open="formOpen" :catalog="catalog" :item="editing" />

        <ConfirmDeleteDialog v-model:open="deleteOpen" :text="deleteText" @confirm="destroy" />
    </AppShell>
</template>
