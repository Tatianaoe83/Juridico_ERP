<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Eye, KeyRound, Pencil, Plus, Search, Trash2, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppShell from '@/Layouts/AppShell.vue';
import PermissionFormDialog from '@/components/app/PermissionFormDialog.vue';
import PermissionShowDialog from '@/components/app/PermissionShowDialog.vue';
import ConfirmDeleteDialog from '@/components/app/ConfirmDeleteDialog.vue';
import { roleMeta } from '@/lib/users';

const props = defineProps({
    /** [{ id, name, label, area, roles: [], created_at }] */
    permissions: { type: Array, required: true },
});

const breadcrumbs = [{ label: 'Inicio', href: '/calendario' }, { label: 'Permisos' }];

/* ---------- Búsqueda y páginas ---------- */

// Son pocos y ya vienen todos: se filtra y pagina aquí, sin ir al servidor.
const PER_PAGE = 10;

const search = ref('');
const page = ref(1);

const filtered = computed(() => {
    const term = search.value.trim().toLowerCase();

    if (!term) return props.permissions;

    return props.permissions.filter((permission) =>
        [permission.name, permission.area].some((text) => text.toLowerCase().includes(term)),
    );
});

const lastPage = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)));

const visible = computed(() => filtered.value.slice((page.value - 1) * PER_PAGE, page.value * PER_PAGE));

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

    // Si no caben ni en su alto natural, la fila crece con su contenido y el card desplaza.
    // Se descuenta el píxel del divisor de cada fila para no provocar scroll.
    rowHeight.value = Math.floor((box.clientHeight - head - PER_PAGE) / PER_PAGE);
}

onMounted(() => {
    observer = new ResizeObserver(measure);

    if (tableBox.value) observer.observe(tableBox.value);
});

onBeforeUnmount(() => observer?.disconnect());

// Buscar vuelve a la primera página; borrar el último de una página, a la anterior.
watch(search, () => (page.value = 1));
watch(lastPage, (last) => page.value > last && (page.value = last));

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

/* ---------- Modales ---------- */

const formOpen = ref(false);
const editing = ref(null);

const showOpen = ref(false);
const viewing = ref(null);

function create() {
    editing.value = null;
    formOpen.value = true;
}

function edit(permission) {
    // Desde el detalle: se cierra uno antes de abrir el otro para no apilar
    // dos diálogos modales, que se pelean por el foco.
    showOpen.value = false;
    editing.value = permission;
    formOpen.value = true;
}

function show(permission) {
    viewing.value = permission;
    showOpen.value = true;
}

/* ---------- Eliminar ---------- */

const deleteOpen = ref(false);
const toDelete = ref(null);
const deleting = ref(null);

function askDelete(permission) {
    toDelete.value = permission;
    deleteOpen.value = true;
}

function destroy() {
    const permission = toDelete.value;

    deleting.value = permission.id;

    router.delete(`/permisos/${permission.id}`, {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
}

/** Avisa de qué roles lo pierden: es lo que cambia para la gente al borrarlo. */
const deleteText = computed(() => {
    const permission = toDelete.value;

    if (!permission) return '';

    const roles = permission.roles.map((role) => roleMeta(role).label).join(', ');

    return `¿Seguro que quieres eliminar el permiso «${permission.name}»?${roles ? ` Se quitará de: ${roles}.` : ''}`;
});

/* Versión compacta de las medidas de usuarios y roles. */
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
    <Head title="Permisos" />

    <AppShell :breadcrumbs="breadcrumbs">
        <div class="flex flex-col md:h-full md:min-h-0">
            <!-- Encabezado -->
            <div class="mb-3 flex shrink-0 flex-wrap items-center justify-between gap-3 tall:mb-4">
                <div class="flex items-center gap-3">
                    <span
                        class="grid size-9 place-content-center rounded-xl bg-gradient-to-br from-brand-light to-brand text-white shadow-md shadow-brand/25 ring-1 ring-white/10"
                    >
                        <KeyRound class="size-4" />
                    </span>
                    <div>
                        <p class="text-[0.6rem] font-bold uppercase tracking-[0.2em] text-slate-400 dark:text-brand-gray/80">Administración</p>
                        <h1 class="text-xl font-bold tracking-tight text-brand dark:text-white">Permisos</h1>
                    </div>
                </div>

                <button
                    type="button"
                    class="inline-flex h-9 cursor-pointer items-center gap-2 rounded-xl bg-brand px-4 text-[0.8rem] font-semibold text-white shadow-md shadow-brand/25 transition-all duration-150 hover:bg-brand/90 hover:shadow-lg hover:shadow-brand/30 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/25 active:translate-y-px dark:bg-brand-light dark:shadow-black/30 dark:hover:bg-brand-light/90"
                    @click="create"
                >
                    <Plus class="size-4" />
                    Nuevo permiso
                </button>
            </div>

            <div
                class="@container flex flex-col overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_1px_3px_rgb(2_29_73/0.04),0_8px_24px_-12px_rgb(2_29_73/0.08)] md:min-h-0 md:flex-1 dark:border-white/[0.08] dark:bg-brand-deep dark:shadow-none"
            >
                <!-- Barra: búsqueda y conteo -->
                <div class="flex shrink-0 flex-wrap items-center justify-between gap-x-4 gap-y-2 px-3 py-2.5 tall:py-3 @2xl:px-4 @6xl:px-6">
                    <label class="relative w-full @xl:max-w-sm">
                        <span class="sr-only">Buscar permisos</span>
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Buscar por nombre o área…"
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
                        <span class="font-bold text-slate-800 dark:text-white">{{ permissions.length }}</span>
                        {{ permissions.length === 1 ? 'permiso definido' : 'permisos definidos' }}
                    </p>
                </div>

                <div ref="tableBox" class="overflow-hidden border-t border-slate-100 md:min-h-0 md:flex-1 md:overflow-y-auto dark:border-white/[0.06]">
                    <table class="w-full text-[0.8rem]">
                        <thead class="sticky top-0 z-10 bg-slate-50 text-slate-500 dark:bg-brand-deep dark:text-brand-gray">
                            <tr>
                                <th :class="TH">Permiso</th>
                                <th :class="[TH, 'hidden @lg:table-cell']">Área</th>
                                <th :class="[TH, 'text-right']">Acciones</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100 dark:divide-white/[0.05]">
                            <tr
                                v-for="permission in visible"
                                :key="permission.id"
                                class="transition-colors duration-150 hover:bg-slate-50/80 dark:hover:bg-white/[0.025]"
                                :class="deleting === permission.id && 'pointer-events-none opacity-40'"
                                :style="rowHeight ? { height: `${rowHeight}px` } : null"
                            >
                                <td :class="[TD, 'w-full max-w-0']">
                                    <div class="flex items-center gap-2.5">
                                        <span
                                            class="grid size-7 shrink-0 place-content-center rounded-lg bg-slate-50 text-slate-500 ring-1 ring-inset ring-slate-500/15 dark:bg-white/[0.05] dark:text-brand-gray dark:ring-white/10"
                                        >
                                            <KeyRound class="size-3.5" />
                                        </span>
                                        <span class="min-w-0">
                                            <code class="block truncate font-mono font-semibold text-slate-800 dark:text-white" :title="permission.name">
                                                {{ permission.name }}
                                            </code>
                                            <!-- En cards angostos el área baja aquí -->
                                            <span class="block truncate text-[0.7rem] text-slate-400 @lg:hidden dark:text-brand-gray/80">{{ permission.area }}</span>
                                        </span>
                                    </div>
                                </td>

                                <td :class="[TD, 'hidden whitespace-nowrap text-slate-600 @lg:table-cell dark:text-slate-300']">
                                    {{ permission.area }}
                                </td>

                                <td :class="TD">
                                    <div class="flex items-center justify-end gap-0.5 @2xl:gap-1">
                                        <button type="button" :class="[ACTION, NEUTRAL]" :aria-label="`Ver ${permission.name}`" title="Ver detalle" @click="show(permission)">
                                            <Eye class="size-4" />
                                        </button>
                                        <button type="button" :class="[ACTION, NEUTRAL]" :aria-label="`Editar ${permission.name}`" title="Editar" @click="edit(permission)">
                                            <Pencil class="size-4" />
                                        </button>
                                        <button
                                            type="button"
                                            :class="[ACTION, 'text-slate-500 hover:bg-red-50 hover:text-red-600 focus-visible:ring-red-500/25 dark:text-brand-gray dark:hover:bg-red-500/10 dark:hover:text-red-300']"
                                            :aria-label="`Eliminar ${permission.name}`"
                                            title="Eliminar"
                                            :disabled="deleting === permission.id"
                                            @click="askDelete(permission)"
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
                                        <Search class="size-5" />
                                    </span>
                                    <p class="text-sm font-bold text-slate-800 dark:text-white">Sin resultados</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-brand-gray">
                                        {{ search ? `Ningún permiso coincide con «${search}».` : 'Aún no hay permisos definidos.' }}
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

        <PermissionFormDialog v-model:open="formOpen" :permission="editing" />

        <PermissionShowDialog v-model:open="showOpen" :permission="viewing" @edit="edit" />

        <ConfirmDeleteDialog v-model:open="deleteOpen" :text="deleteText" @confirm="destroy" />
    </AppShell>
</template>
