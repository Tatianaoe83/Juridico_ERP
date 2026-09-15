<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Eye, Pencil, Search, Trash2, UserPlus, Users, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppShell from '@/Layouts/AppShell.vue';
import UserFormDialog from '@/components/app/UserFormDialog.vue';
import UserShowDialog from '@/components/app/UserShowDialog.vue';
import { useSwal } from '@/composables/useSwal';
import { roleMeta, roleTone, userInitials } from '@/lib/users';

const props = defineProps({
    users: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    /** Nombres de rol existentes, para el formulario. */
    roles: { type: Array, default: () => [] },
    /** Repartir roles es más sensible que editar: solo `roles.manage`. */
    canManageRoles: { type: Boolean, default: false },
    canCreate: { type: Boolean, default: false },
});

const breadcrumbs = [{ label: 'Inicio', href: '/calendario' }, { label: 'Usuarios' }];

const { confirmDelete, blocks } = useSwal();

/* ---------- Búsqueda y páginas ---------- */

// El servidor pagina de 10 en 10 por defecto, igual que permisos y roles.
const PER_PAGE = 10;

const search = ref(props.filters.search ?? '');

function visit(params) {
    router.get(
        '/usuarios',
        { ...(search.value ? { search: search.value } : {}), ...params },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

let timer;

// Se espera a que deje de teclear: una petición por letra satura el servidor
// y hace parpadear la tabla.
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(() => visit({}), 350);
});

function goTo(page) {
    visit({ page });
}

const range = computed(() => {
    const { current_page: page, per_page: size, total } = props.users.meta;
    const from = total ? (page - 1) * size + 1 : 0;

    return { from, to: from ? from + props.users.data.length - 1 : 0, total };
});

/** 1 … 4 5 6 … 12: la actual, sus vecinas y los extremos. */
const pages = computed(() => {
    const { current_page: current, last_page: last } = props.users.meta;
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

/* ---------- Modales ---------- */

const formOpen = ref(false);
const editing = ref(null);

const showOpen = ref(false);
const viewing = ref(null);

function create() {
    editing.value = null;
    formOpen.value = true;
}

function edit(user) {
    // Desde el detalle: se cierra uno antes de abrir el otro para no apilar
    // dos diálogos modales, que se pelean por el foco.
    showOpen.value = false;
    editing.value = user;
    formOpen.value = true;
}

function show(user) {
    viewing.value = user;
    showOpen.value = true;
}

/* ---------- Eliminar ---------- */

const deleting = ref(null);

async function destroy(user) {
    const { lead, panel, note, stack } = blocks;

    const ok = await confirmDelete({
        title: '¿Seguro que quieres eliminar a este usuario?',
        html: stack(
            lead(`<span class="font-semibold">${escapeHtml(user.name)}</span><br><span class="text-muted-foreground">${escapeHtml(user.email)}</span>`),
            panel({
                label: 'Qué se pierde',
                tone: 'danger',
                items: ['Ya no podrá iniciar sesión', 'Se cierran sus sesiones y tokens de acceso'],
            }),
            note('Esta acción no se puede deshacer.'),
        ),
        confirmText: 'Sí, eliminar',
    });

    if (!ok) return;

    deleting.value = user.id;

    router.delete(`/usuarios/${user.id}`, {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
}

/** El nombre lo escribe un usuario y va dentro de HTML del SweetAlert. */
function escapeHtml(text = '') {
    return text.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
}

function shortDate(iso) {
    return iso ? new Date(iso).toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';
}

/*
 * Mismas medidas que la tabla de permisos. Responden al ancho del card
 * (`@container`), no al de la ventana: con el sidebar abierto el card es más
 * angosto que la pantalla.
 */
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
    <Head title="Usuarios" />

    <AppShell :breadcrumbs="breadcrumbs">
        <div class="flex flex-col md:h-full md:min-h-0">
            <!-- Encabezado -->
            <div class="mb-3 flex shrink-0 flex-wrap items-center justify-between gap-3 tall:mb-4">
                <div class="flex items-center gap-3">
                    <span
                        class="grid size-9 place-content-center rounded-xl bg-gradient-to-br from-brand-light to-brand text-white shadow-md shadow-brand/25 ring-1 ring-white/10"
                    >
                        <Users class="size-4" />
                    </span>
                    <div>
                        <p class="text-[0.6rem] font-bold uppercase tracking-[0.2em] text-slate-400 dark:text-brand-gray/80">Administración</p>
                        <h1 class="text-xl font-bold tracking-tight text-brand dark:text-white">Usuarios</h1>
                    </div>
                </div>

                <button
                    v-if="canCreate"
                    type="button"
                    class="inline-flex h-9 cursor-pointer items-center gap-2 rounded-xl bg-brand px-4 text-[0.8rem] font-semibold text-white shadow-md shadow-brand/25 transition-all duration-150 hover:bg-brand/90 hover:shadow-lg hover:shadow-brand/30 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/25 active:translate-y-px dark:bg-brand-light dark:shadow-black/30 dark:hover:bg-brand-light/90"
                    @click="create"
                >
                    <UserPlus class="size-4" />
                    Nuevo usuario
                </button>
            </div>

            <div
                class="@container flex flex-col overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_1px_3px_rgb(2_29_73/0.04),0_8px_24px_-12px_rgb(2_29_73/0.08)] md:min-h-0 md:flex-1 dark:border-white/[0.08] dark:bg-brand-deep dark:shadow-none"
            >
                <!-- Barra: búsqueda y conteo -->
                <div class="flex shrink-0 flex-wrap items-center justify-between gap-x-4 gap-y-2 px-3 py-2.5 tall:py-3 @2xl:px-4 @6xl:px-6">
                    <label class="relative w-full @xl:max-w-sm">
                        <span class="sr-only">Buscar usuarios</span>
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Buscar por nombre o correo…"
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
                        <span class="font-bold text-slate-800 dark:text-white">{{ range.total }}</span>
                        {{ range.total === 1 ? 'cuenta registrada' : 'cuentas registradas' }}
                    </p>
                </div>

                <!-- Sin scroll horizontal: las columnas que no caben se esconden y su dato baja debajo del nombre -->
                <div ref="tableBox" class="overflow-hidden border-t border-slate-100 md:min-h-0 md:flex-1 md:overflow-y-auto dark:border-white/[0.06]">
                    <table class="w-full text-[0.8rem]">
                        <thead class="sticky top-0 z-10 bg-slate-50 text-slate-500 dark:bg-brand-deep dark:text-brand-gray">
                            <tr>
                                <th :class="TH">Usuario</th>
                                <th :class="[TH, 'hidden @4xl:table-cell']">Correo electrónico</th>
                                <th :class="[TH, 'hidden @lg:table-cell']">Rol</th>
                                <th :class="[TH, 'hidden @3xl:table-cell']">Fecha de alta</th>
                                <th :class="[TH, 'text-right']">Acciones</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100 dark:divide-white/[0.05]">
                            <tr
                                v-for="user in users.data"
                                :key="user.id"
                                class="transition-colors duration-150 hover:bg-slate-50/80 dark:hover:bg-white/[0.025]"
                                :class="deleting === user.id && 'pointer-events-none opacity-40'"
                                :style="rowHeight ? { height: `${rowHeight}px` } : null"
                            >
                                <!-- w-full + max-w-0: la columna toma el espacio libre y trunca en vez de estirar la tabla -->
                                <td :class="[TD, 'w-full max-w-0']">
                                    <div class="flex items-center gap-2.5">
                                        <span
                                            class="grid size-7 shrink-0 place-content-center rounded-lg bg-gradient-to-br from-brand-light to-brand text-[0.65rem] font-bold text-white shadow-sm shadow-brand/20"
                                        >
                                            {{ userInitials(user.name) }}
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="flex items-center gap-2">
                                                <span class="truncate font-semibold text-slate-800 dark:text-white" :title="user.name">
                                                    {{ user.name }}
                                                </span>
                                                <span
                                                    v-if="user.is_self"
                                                    class="shrink-0 rounded-md bg-brand/[0.07] px-1.5 py-0.5 text-[0.55rem] font-bold tracking-wide text-brand uppercase dark:bg-white/10 dark:text-brand-gray"
                                                >
                                                    Tú
                                                </span>
                                            </span>
                                            <!-- Si el card es ancho, el correo y el rol tienen columna propia -->
                                            <span class="block truncate text-[0.7rem] text-slate-400 @4xl:hidden dark:text-brand-gray/80" :title="user.email">
                                                {{ user.email }}<span v-if="user.roles.length" class="@lg:hidden"> · {{ user.roles.map((role) => roleMeta(role).label).join(', ') }}</span>
                                            </span>
                                        </span>
                                    </div>
                                </td>

                                <td :class="[TD, 'hidden @4xl:table-cell']">
                                    <span class="block max-w-[18rem] truncate text-slate-600 dark:text-slate-300" :title="user.email">{{ user.email }}</span>
                                </td>

                                <td :class="[TD, 'hidden @lg:table-cell']">
                                    <span
                                        v-for="role in user.roles"
                                        :key="role"
                                        class="mr-1.5 inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[0.7rem] font-bold whitespace-nowrap ring-1 ring-inset"
                                        :class="roleTone(role)"
                                    >
                                        <component :is="roleMeta(role).icon" class="size-3" />
                                        {{ roleMeta(role).label }}
                                    </span>
                                    <span v-if="!user.roles.length" class="text-xs whitespace-nowrap text-slate-400 dark:text-brand-gray">Sin rol</span>
                                </td>

                                <td :class="[TD, 'hidden whitespace-nowrap text-slate-600 @3xl:table-cell dark:text-slate-300']">
                                    {{ shortDate(user.created_at) }}
                                </td>

                                <td :class="TD">
                                    <div class="flex items-center justify-end gap-0.5 @2xl:gap-1">
                                        <button type="button" :class="[ACTION, NEUTRAL]" :aria-label="`Ver a ${user.name}`" title="Ver detalle" @click="show(user)">
                                            <Eye class="size-4" />
                                        </button>
                                        <button
                                            v-if="user.can.update"
                                            type="button"
                                            :class="[ACTION, NEUTRAL]"
                                            :aria-label="`Editar a ${user.name}`"
                                            title="Editar"
                                            @click="edit(user)"
                                        >
                                            <Pencil class="size-4" />
                                        </button>
                                        <button
                                            v-if="user.can.delete"
                                            type="button"
                                            :class="[ACTION, 'text-slate-500 hover:bg-red-50 hover:text-red-600 focus-visible:ring-red-500/25 dark:text-brand-gray dark:hover:bg-red-500/10 dark:hover:text-red-300']"
                                            :aria-label="`Eliminar a ${user.name}`"
                                            title="Eliminar"
                                            :disabled="deleting === user.id"
                                            @click="destroy(user)"
                                        >
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!users.data.length">
                                <td colspan="5" class="px-6 py-8 text-center tall:py-14">
                                    <span
                                        class="mx-auto mb-2.5 grid size-10 place-content-center rounded-xl bg-slate-100 text-slate-400 dark:bg-white/[0.05] dark:text-brand-gray"
                                    >
                                        <Search class="size-5" />
                                    </span>
                                    <p class="text-sm font-bold text-slate-800 dark:text-white">Sin resultados</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-brand-gray">
                                        {{ search ? `Ninguna cuenta coincide con «${search}».` : 'Aún no hay cuentas registradas.' }}
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
                    v-if="users.data.length"
                    class="flex shrink-0 flex-wrap items-center justify-between gap-x-4 gap-y-2 border-t border-slate-100 px-3 py-2 text-xs text-slate-500 tall:py-2.5 @2xl:px-4 @6xl:px-6 dark:border-white/[0.06] dark:text-brand-gray"
                >
                    <p>
                        Mostrando
                        <span class="font-semibold text-slate-800 tabular-nums dark:text-white">{{ range.from }}–{{ range.to }}</span>
                        de
                        <span class="font-semibold text-slate-800 tabular-nums dark:text-white">{{ range.total }}</span>
                    </p>

                    <nav v-if="users.meta.last_page > 1" aria-label="Paginación" class="flex items-center gap-1">
                        <button
                            type="button"
                            :class="[PAGE_BTN, 'hover:bg-slate-100 hover:text-brand dark:hover:bg-white/[0.06] dark:hover:text-white']"
                            :disabled="users.meta.current_page <= 1"
                            aria-label="Página anterior"
                            @click="goTo(users.meta.current_page - 1)"
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
                                    page === users.meta.current_page
                                        ? 'bg-brand text-white shadow-md shadow-brand/25 dark:bg-brand-light'
                                        : 'hover:bg-slate-100 hover:text-brand dark:hover:bg-white/[0.06] dark:hover:text-white',
                                ]"
                                :aria-current="page === users.meta.current_page ? 'page' : undefined"
                                @click="goTo(page)"
                            >
                                {{ page }}
                            </button>
                        </template>

                        <button
                            type="button"
                            :class="[PAGE_BTN, 'hover:bg-slate-100 hover:text-brand dark:hover:bg-white/[0.06] dark:hover:text-white']"
                            :disabled="users.meta.current_page >= users.meta.last_page"
                            aria-label="Página siguiente"
                            @click="goTo(users.meta.current_page + 1)"
                        >
                            <ChevronRight class="size-3.5" />
                        </button>
                    </nav>
                </div>
            </div>
        </div>

        <UserFormDialog
            v-model:open="formOpen"
            :user="editing"
            :roles="roles"
            :can-manage-roles="canManageRoles"
        />

        <UserShowDialog v-model:open="showOpen" :user="viewing" @edit="edit" />
    </AppShell>
</template>
