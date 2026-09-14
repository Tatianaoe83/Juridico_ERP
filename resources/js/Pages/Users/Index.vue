<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Eye, Pencil, Search, Trash2, UserPlus, Users, X } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
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

const search = ref(props.filters.search ?? '');

/** Filas por página que caben en pantalla; null = lo que decida el servidor. */
const perPage = ref(null);

function visit(params) {
    router.get(
        '/usuarios',
        {
            ...(search.value ? { search: search.value } : {}),
            ...(perPage.value ? { per_page: perPage.value } : {}),
            ...params,
        },
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

/* ---------- Que la tabla quepa sin scroll ---------- */

/**
 * En computadora la página no debe desplazarse: se mide el alto que le queda
 * al cuerpo de la tabla y se piden al servidor justo las filas que caben. Lo
 * demás pasa a la paginación. En móvil no aplica: ahí desplazar es lo normal.
 */
const DESKTOP = '(min-width: 768px)';
const MIN_ROWS = 3;

const tableBox = ref(null);
let observer;
let fitTimer;

function fit() {
    const box = tableBox.value;

    if (!box || !window.matchMedia(DESKTOP).matches) return;

    const head = box.querySelector('thead')?.offsetHeight ?? 0;
    const row = box.querySelector('tbody tr[data-row]')?.offsetHeight;

    // Sin filas (búsqueda vacía) no hay con qué medir: se espera a la próxima.
    if (!row) return;

    const rows = Math.max(MIN_ROWS, Math.floor((box.clientHeight - head) / row));

    if (rows === props.users.meta.per_page) return;

    perPage.value = rows;

    // Conserva a la vista el primer registro que se estaba mirando.
    const first = (props.users.meta.current_page - 1) * props.users.meta.per_page;

    visit({ page: Math.floor(first / rows) + 1 });
}

// Al llegar datos nuevos se vuelve a medir: si la búsqueda anterior no dejó
// filas no hubo con qué calcular. Converge porque solo pide cuando cambia.
watch(() => props.users, () => nextTick(fit));

onMounted(() => {
    observer = new ResizeObserver(() => {
        clearTimeout(fitTimer);
        fitTimer = setTimeout(fit, 150);
    });

    if (tableBox.value) observer.observe(tableBox.value);
});

onBeforeUnmount(() => {
    observer?.disconnect();
    clearTimeout(fitTimer);
    clearTimeout(timer);
});

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
 * Los tamaños responden al ancho del card (`@container`), no al de la
 * ventana: con el sidebar abierto el card es más angosto que la pantalla, y
 * medir la ventana dejaba columnas que no cabían y un scroll horizontal.
 * En alto: compacto por defecto, holgado solo en ventanas altas.
 */
const TH = 'px-3 py-3 text-left text-[0.68rem] font-bold uppercase tracking-[0.14em] whitespace-nowrap tall:py-4 @2xl:px-5 @6xl:px-8';
const TD = 'px-3 py-2.5 tall:py-4 @2xl:px-5 @6xl:px-8';

const ACTION =
    'grid size-8 @2xl:size-9 cursor-pointer place-content-center rounded-xl transition-colors duration-150 ' +
    'focus-visible:outline-none focus-visible:ring-[3px] disabled:pointer-events-none disabled:opacity-40';

const PAGE_BTN =
    'grid h-8 min-w-8 @2xl:h-9 @2xl:min-w-9 cursor-pointer place-content-center rounded-xl px-2.5 text-sm font-semibold tabular-nums transition-colors duration-150 ' +
    'focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-brand/25 disabled:pointer-events-none disabled:opacity-40';
</script>

<template>
    <Head title="Usuarios" />

    <AppShell :breadcrumbs="breadcrumbs">
        <!--
            En computadora la página ocupa exacto el alto de <main> y la tabla
            toma el resto: nada desplaza, las filas se ajustan al espacio.
        -->
        <div class="flex flex-col md:h-full md:min-h-0">
            <!-- Encabezado -->
            <div class="mb-4 flex shrink-0 flex-wrap items-center justify-between gap-4 tall:mb-6">
                <div class="flex items-center gap-3.5">
                    <span
                        class="grid size-11 place-content-center rounded-xl bg-gradient-to-br from-brand-light to-brand text-white shadow-lg shadow-brand/25 ring-1 ring-white/10 tall:size-13 tall:rounded-2xl"
                    >
                        <Users class="size-5" />
                    </span>
                    <div>
                        <p class="text-[0.65rem] font-bold uppercase tracking-[0.2em] text-slate-400 dark:text-brand-gray/80">Administración</p>
                        <h1 class="text-2xl font-bold tracking-tight text-brand tall:text-3xl dark:text-white">Usuarios</h1>
                    </div>
                </div>

                <button
                    v-if="canCreate"
                    type="button"
                    class="inline-flex h-10 cursor-pointer items-center tall:h-11 gap-2 rounded-xl bg-brand px-5 text-sm font-semibold text-white shadow-lg shadow-brand/25 transition-all duration-150 hover:bg-brand/90 hover:shadow-xl hover:shadow-brand/30 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/25 active:translate-y-px dark:bg-brand-light dark:shadow-black/30 dark:hover:bg-brand-light/90"
                    @click="create"
                >
                    <UserPlus class="size-4" />
                    Nuevo usuario
                </button>
            </div>

            <div
                class="@container flex flex-col overflow-hidden rounded-2xl border border-slate-200/80 bg-white md:min-h-0 md:flex-1 shadow-[0_1px_3px_rgb(2_29_73/0.04),0_8px_24px_-12px_rgb(2_29_73/0.08)] dark:border-white/[0.08] dark:bg-brand-deep dark:shadow-none"
            >
                <!-- Barra: búsqueda y conteo -->
                <div class="flex shrink-0 flex-wrap items-center justify-between gap-x-4 gap-y-2 px-3 py-3.5 tall:py-5 @2xl:px-5 @6xl:px-8">
                    <label class="relative w-full @xl:max-w-md">
                        <span class="sr-only">Buscar usuarios</span>
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Buscar por nombre o correo…"
                            class="peer h-10 tall:h-11 w-full rounded-xl border border-slate-200 bg-slate-50/70 pr-10 pl-11 text-sm text-slate-900 outline-none transition-[border-color,background-color,box-shadow] duration-150 placeholder:text-slate-400 hover:border-slate-300 focus:border-brand/50 focus:bg-white focus:ring-4 focus:ring-brand/10 dark:border-white/10 dark:bg-white/[0.04] dark:text-white dark:placeholder:text-white/30 dark:hover:border-white/20 dark:focus:border-brand-gray/50 dark:focus:bg-white/[0.06] dark:focus:ring-white/10 [&::-webkit-search-cancel-button]:hidden"
                        />
                        <Search
                            class="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-slate-400 transition-colors peer-focus:text-brand dark:text-white/35 dark:peer-focus:text-white"
                        />
                        <button
                            v-if="search"
                            type="button"
                            class="absolute top-1/2 right-2 grid size-7 -translate-y-1/2 cursor-pointer place-content-center rounded-lg text-slate-400 hover:bg-slate-200/60 hover:text-slate-700 dark:hover:bg-white/10 dark:hover:text-white"
                            aria-label="Limpiar búsqueda"
                            @click="search = ''"
                        >
                            <X class="size-4" />
                        </button>
                    </label>

                    <p class="text-sm text-slate-500 dark:text-brand-gray">
                        <span class="font-bold text-slate-800 dark:text-white">{{ range.total }}</span>
                        {{ range.total === 1 ? 'cuenta registrada' : 'cuentas registradas' }}
                    </p>
                </div>

                <!--
                    Se mide este contenedor para saber cuántas filas caben. Sin
                    scroll en ningún eje: las columnas que no caben a lo ancho
                    se esconden y su dato baja debajo del nombre.
                -->
                <div
                    ref="tableBox"
                    class="overflow-hidden border-t border-slate-100 md:min-h-0 md:flex-1 dark:border-white/[0.06]"
                >
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50/80 text-slate-500 dark:bg-white/[0.025] dark:text-brand-gray">
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
                                data-row
                                class="group transition-colors duration-150 hover:bg-slate-50/80 dark:hover:bg-white/[0.025]"
                                :class="deleting === user.id && 'pointer-events-none opacity-40'"
                            >
                                <!-- w-full + max-w-0: la columna toma el espacio libre y trunca en vez de estirar la tabla -->
                                <td :class="[TD, 'w-full max-w-0']">
                                    <div class="flex items-center gap-3 @2xl:gap-4">
                                        <span
                                            class="grid size-8 shrink-0 place-content-center rounded-lg bg-gradient-to-br from-brand-light to-brand text-[0.7rem] font-bold text-white shadow-md shadow-brand/20 @2xl:size-9 @2xl:text-xs tall:@2xl:size-11 tall:@2xl:rounded-xl tall:@2xl:text-sm"
                                        >
                                            {{ userInitials(user.name) }}
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="flex items-center gap-2">
                                                <span class="truncate text-sm font-bold text-slate-800 @2xl:text-[0.95rem] dark:text-white" :title="user.name">
                                                    {{ user.name }}
                                                </span>
                                                <span
                                                    v-if="user.is_self"
                                                    class="shrink-0 rounded-md bg-brand/[0.07] px-1.5 py-0.5 text-[0.6rem] font-bold tracking-wide text-brand uppercase dark:bg-white/10 dark:text-brand-gray"
                                                >
                                                    Tú
                                                </span>
                                            </span>
                                            <!-- Si el card es ancho, el correo y el rol tienen columna propia -->
                                            <span class="mt-0.5 block truncate text-xs text-slate-500 @2xl:text-[0.8rem] @4xl:hidden dark:text-brand-gray" :title="user.email">
                                                {{ user.email }}
                                            </span>
                                            <span v-if="user.roles.length" class="mt-1 block truncate text-[0.7rem] font-semibold text-brand @lg:hidden dark:text-brand-gray">
                                                {{ user.roles.map((role) => roleMeta(role).label).join(', ') }}
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
                                        class="mr-1.5 inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-bold whitespace-nowrap ring-1 ring-inset @2xl:px-2.5"
                                        :class="roleTone(role)"
                                    >
                                        <component :is="roleMeta(role).icon" class="hidden size-3.5 @2xl:block" />
                                        {{ roleMeta(role).label }}
                                    </span>
                                    <span v-if="!user.roles.length" class="text-xs whitespace-nowrap text-slate-400 dark:text-brand-gray">Sin rol</span>
                                </td>

                                <td :class="[TD, 'hidden whitespace-nowrap text-slate-600 @3xl:table-cell dark:text-slate-300']">
                                    {{ shortDate(user.created_at) }}
                                </td>

                                <td :class="TD">
                                    <div class="flex items-center justify-end gap-0.5 @2xl:gap-1.5">
                                        <button
                                            type="button"
                                            :class="[ACTION, 'text-slate-500 hover:bg-brand/[0.07] hover:text-brand focus-visible:ring-brand/25 dark:text-brand-gray dark:hover:bg-white/10 dark:hover:text-white']"
                                            :aria-label="`Ver a ${user.name}`"
                                            title="Ver detalle"
                                            @click="show(user)"
                                        >
                                            <Eye class="size-[1.1rem]" />
                                        </button>
                                        <button
                                            v-if="user.can.update"
                                            type="button"
                                            :class="[ACTION, 'text-slate-500 hover:bg-brand/[0.07] hover:text-brand focus-visible:ring-brand/25 dark:text-brand-gray dark:hover:bg-white/10 dark:hover:text-white']"
                                            :aria-label="`Editar a ${user.name}`"
                                            title="Editar"
                                            @click="edit(user)"
                                        >
                                            <Pencil class="size-[1.1rem]" />
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
                                            <Trash2 class="size-[1.1rem]" />
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!users.data.length">
                                <td colspan="5" class="px-6 py-10 text-center tall:py-20">
                                    <span
                                        class="mx-auto mb-3 grid size-12 place-content-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-white/[0.05] dark:text-brand-gray"
                                    >
                                        <Search class="size-6" />
                                    </span>
                                    <p class="text-base font-bold text-slate-800 dark:text-white">Sin resultados</p>
                                    <p class="mt-1 text-sm text-slate-500 dark:text-brand-gray">
                                        {{ search ? `Ninguna cuenta coincide con «${search}».` : 'Aún no hay cuentas registradas.' }}
                                    </p>
                                    <button
                                        v-if="search"
                                        type="button"
                                        class="mt-4 cursor-pointer text-sm font-semibold text-brand hover:underline dark:text-white"
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
                    class="flex shrink-0 flex-wrap items-center justify-between gap-x-4 gap-y-2 border-t border-slate-100 px-3 py-2.5 text-xs tall:py-4 text-slate-500 @2xl:px-5 @2xl:text-sm @6xl:px-8 dark:border-white/[0.06] dark:text-brand-gray"
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
                            <ChevronLeft class="size-4" />
                        </button>

                        <template v-for="(page, i) in pages" :key="`${page}-${i}`">
                            <span v-if="page === '…'" class="px-1 text-slate-400 @max-md:hidden">…</span>
                            <button
                                v-else
                                type="button"
                                :class="[
                                    PAGE_BTN,
                                    page === users.meta.current_page
                                        ? 'bg-brand text-white shadow-md shadow-brand/25 dark:bg-brand-light'
                                        : 'hover:bg-slate-100 hover:text-brand @max-md:hidden dark:hover:bg-white/[0.06] dark:hover:text-white',
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
                            <ChevronRight class="size-4" />
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
