<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Eye, Lock, Pencil, Plus, Search, ShieldCheck, Trash2, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppShell from '@/Layouts/AppShell.vue';
import { useSwal } from '@/composables/useSwal';
import { roleMeta, roleTone } from '@/lib/users';

const props = defineProps({
    /** [{ id, name, users_count, permissions_count, unrestricted, system, can }] */
    roles: { type: Array, required: true },
    totalPermissions: { type: Number, default: 0 },
});

const breadcrumbs = [{ label: 'Inicio', href: '/calendario' }, { label: 'Roles' }];

const { confirmDelete, blocks } = useSwal();

/* ---------- Búsqueda y páginas ---------- */

// Son pocos y ya vienen todos: se filtra y pagina aquí, sin ir al servidor.
const PER_PAGE = 10;

const search = ref('');
const page = ref(1);

const filtered = computed(() => {
    const term = search.value.trim().toLowerCase();

    if (!term) return props.roles;

    return props.roles.filter((role) =>
        [role.name, roleMeta(role.name).label].some((text) => text.toLowerCase().includes(term)),
    );
});

const lastPage = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)));

const visible = computed(() => filtered.value.slice((page.value - 1) * PER_PAGE, page.value * PER_PAGE));

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

onBeforeUnmount(() => observer?.disconnect());

/* ---------- Eliminar ---------- */

const deleting = ref(null);

async function destroy(role) {
    const { lead, panel, note, stack } = blocks;

    const ok = await confirmDelete({
        title: '¿Seguro que quieres eliminar este rol?',
        html: stack(
            lead(`<span class="font-semibold">${escapeHtml(roleMeta(role.name).label)}</span>`),
            panel({
                label: 'Qué se pierde',
                tone: 'danger',
                items: [
                    `Sus ${role.permissions_count} permisos asignados`,
                    'No se podrá asignar a nuevos usuarios',
                ],
            }),
            note('Esta acción no se puede deshacer.'),
        ),
        confirmText: 'Sí, eliminar',
    });

    if (!ok) return;

    deleting.value = role.id;

    router.delete(`/roles/${role.id}`, {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
}

/** El nombre de un rol lo escribe una persona y va dentro de HTML. */
function escapeHtml(text = '') {
    return text.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
}

const people = (n) => (n === 1 ? '1 usuario' : `${n} usuarios`);

/** Por qué no se puede borrar, para el título del botón deshabilitado. */
function deleteBlocker(role) {
    if (role.system) return 'Los roles del sistema no se pueden eliminar';
    if (role.users_count) return `Tiene ${people(role.users_count)}: reasígnalos primero`;

    return 'Eliminar';
}

/* Mismas medidas que la tabla de permisos: responden al ancho del card. */
const TH = 'px-3 py-2 text-left text-[0.62rem] font-bold uppercase tracking-[0.14em] whitespace-nowrap tall:py-2.5 @2xl:px-4 @6xl:px-6';
const TD = 'px-3 py-1.5 tall:py-2 @2xl:px-4 @6xl:px-6';

const ACTION =
    'grid size-7 cursor-pointer place-content-center rounded-lg transition-colors duration-150 ' +
    'focus-visible:outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-35';

const NEUTRAL =
    'text-slate-500 hover:bg-brand/[0.07] hover:text-brand focus-visible:ring-brand/25 dark:text-brand-gray dark:hover:bg-white/10 dark:hover:text-white';

const PAGE_BTN =
    'grid h-7 min-w-7 cursor-pointer place-content-center rounded-lg px-2 text-xs font-semibold tabular-nums transition-colors duration-150 ' +
    'focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-brand/25 disabled:pointer-events-none disabled:opacity-40';
</script>

<template>
    <Head title="Roles" />

    <AppShell :breadcrumbs="breadcrumbs">
        <div class="flex flex-col md:h-full md:min-h-0">
            <!-- Encabezado -->
            <div class="mb-3 flex shrink-0 flex-wrap items-center justify-between gap-3 tall:mb-4">
                <div class="flex items-center gap-3">
                    <span
                        class="grid size-9 place-content-center rounded-xl bg-gradient-to-br from-brand-light to-brand text-white shadow-md shadow-brand/25 ring-1 ring-white/10"
                    >
                        <ShieldCheck class="size-4" />
                    </span>
                    <div>
                        <p class="text-[0.6rem] font-bold uppercase tracking-[0.2em] text-slate-400 dark:text-brand-gray/80">Administración</p>
                        <h1 class="text-xl font-bold tracking-tight text-brand dark:text-white">Roles</h1>
                    </div>
                </div>

                <Link
                    href="/roles/crear"
                    class="inline-flex h-9 items-center gap-2 rounded-xl bg-brand px-4 text-[0.8rem] font-semibold text-white shadow-md shadow-brand/25 transition-all duration-150 hover:bg-brand/90 hover:shadow-lg hover:shadow-brand/30 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/25 active:translate-y-px dark:bg-brand-light dark:shadow-black/30 dark:hover:bg-brand-light/90"
                >
                    <Plus class="size-4" />
                    Nuevo rol
                </Link>
            </div>

            <div
                class="@container flex flex-col overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_1px_3px_rgb(2_29_73/0.04),0_8px_24px_-12px_rgb(2_29_73/0.08)] md:min-h-0 md:flex-1 dark:border-white/[0.08] dark:bg-brand-deep dark:shadow-none"
            >
                <!-- Barra: búsqueda y conteo -->
                <div class="flex shrink-0 flex-wrap items-center justify-between gap-x-4 gap-y-2 px-3 py-2.5 tall:py-3 @2xl:px-4 @6xl:px-6">
                    <label class="relative w-full @xl:max-w-sm">
                        <span class="sr-only">Buscar roles</span>
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Buscar rol…"
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
                        <span class="font-bold text-slate-800 dark:text-white">{{ roles.length }}</span>
                        {{ roles.length === 1 ? 'rol definido' : 'roles definidos' }}
                    </p>
                </div>

                <div ref="tableBox" class="overflow-hidden border-t border-slate-100 md:min-h-0 md:flex-1 md:overflow-y-auto dark:border-white/[0.06]">
                    <table class="w-full text-[0.8rem]">
                        <thead class="sticky top-0 z-10 bg-slate-50 text-slate-500 dark:bg-brand-deep dark:text-brand-gray">
                            <tr>
                                <th :class="TH">Rol</th>
                                <th :class="[TH, 'hidden @lg:table-cell']">Usuarios</th>
                                <th :class="[TH, 'hidden @2xl:table-cell']">Permisos</th>
                                <th :class="[TH, 'text-right']">Acciones</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100 dark:divide-white/[0.05]">
                            <tr
                                v-for="role in visible"
                                :key="role.id"
                                class="transition-colors duration-150 hover:bg-slate-50/80 dark:hover:bg-white/[0.025]"
                                :class="deleting === role.id && 'pointer-events-none opacity-40'"
                                :style="rowHeight ? { height: `${rowHeight}px` } : null"
                            >
                                <td :class="[TD, 'w-full max-w-0']">
                                    <div class="flex items-center gap-2.5">
                                        <span class="grid size-7 shrink-0 place-content-center rounded-lg ring-1 ring-inset" :class="roleTone(role.name)">
                                            <component :is="roleMeta(role.name).icon" class="size-3.5" />
                                        </span>
                                        <span class="min-w-0">
                                            <span class="flex items-center gap-2">
                                                <span class="truncate font-semibold text-slate-800 dark:text-white">
                                                    {{ roleMeta(role.name).label }}
                                                </span>
                                                <span
                                                    v-if="role.system"
                                                    class="inline-flex shrink-0 items-center gap-1 rounded-md bg-slate-100 px-1.5 py-0.5 text-[0.55rem] font-bold tracking-wide text-slate-500 uppercase dark:bg-white/10 dark:text-brand-gray"
                                                    title="Rol del sistema: no se renombra ni se elimina"
                                                >
                                                    <Lock class="size-2.5" />
                                                    Sistema
                                                </span>
                                            </span>
                                            <code class="block truncate font-mono text-[0.7rem] text-slate-400 dark:text-brand-gray/80">
                                                {{ role.name }}<span class="@2xl:hidden"> · {{ people(role.users_count) }} · {{ role.unrestricted ? 'acceso total' : `${role.permissions_count} permisos` }}</span>
                                            </code>
                                        </span>
                                    </div>
                                </td>

                                <td :class="[TD, 'hidden whitespace-nowrap @lg:table-cell']">
                                    <span class="font-bold text-slate-800 tabular-nums dark:text-white">{{ role.users_count }}</span>
                                    <span class="ml-1 text-xs text-slate-500 dark:text-brand-gray">{{ role.users_count === 1 ? 'usuario' : 'usuarios' }}</span>
                                </td>

                                <td :class="[TD, 'hidden @2xl:table-cell']">
                                    <span
                                        v-if="role.unrestricted"
                                        class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[0.7rem] font-bold whitespace-nowrap ring-1 ring-inset"
                                        :class="roleTone('superadmin')"
                                    >
                                        Acceso total
                                    </span>
                                    <div v-else class="w-28">
                                        <div class="flex items-baseline justify-between text-[0.7rem]">
                                            <span class="font-bold text-slate-800 tabular-nums dark:text-white">{{ role.permissions_count }}</span>
                                            <span class="text-slate-400 tabular-nums dark:text-brand-gray/70">de {{ totalPermissions }}</span>
                                        </div>
                                        <div class="mt-1 h-1 overflow-hidden rounded-full bg-slate-100 dark:bg-white/[0.08]">
                                            <div
                                                class="h-full rounded-full bg-gradient-to-r from-brand-light to-brand transition-[width] duration-500 dark:from-brand-gray dark:to-brand-light"
                                                :style="{ width: `${totalPermissions ? (role.permissions_count / totalPermissions) * 100 : 0}%` }"
                                            />
                                        </div>
                                    </div>
                                </td>

                                <td :class="TD">
                                    <div class="flex items-center justify-end gap-0.5 @2xl:gap-1">
                                        <Link
                                            :href="`/roles/${role.id}`"
                                            :class="[ACTION, NEUTRAL]"
                                            :aria-label="`Ver ${roleMeta(role.name).label}`"
                                            title="Ver detalle"
                                        >
                                            <Eye class="size-4" />
                                        </Link>
                                        <Link
                                            v-if="role.can.update"
                                            :href="`/roles/${role.id}/editar`"
                                            :class="[ACTION, NEUTRAL]"
                                            :aria-label="`Editar ${roleMeta(role.name).label}`"
                                            title="Editar permisos"
                                        >
                                            <Pencil class="size-4" />
                                        </Link>
                                        <!-- Deshabilitado con motivo: esconderlo dejaría la duda de por qué no está -->
                                        <button
                                            type="button"
                                            :class="[ACTION, 'text-slate-500 hover:bg-red-50 hover:text-red-600 focus-visible:ring-red-500/25 dark:text-brand-gray dark:hover:bg-red-500/10 dark:hover:text-red-300']"
                                            :aria-label="`Eliminar ${roleMeta(role.name).label}`"
                                            :title="deleteBlocker(role)"
                                            :disabled="!role.can.delete || deleting === role.id"
                                            @click="destroy(role)"
                                        >
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!visible.length">
                                <td colspan="4" class="px-6 py-8 text-center tall:py-14">
                                    <span
                                        class="mx-auto mb-2.5 grid size-10 place-content-center rounded-xl bg-slate-100 text-slate-400 dark:bg-white/[0.05] dark:text-brand-gray"
                                    >
                                        <Search class="size-5" />
                                    </span>
                                    <p class="text-sm font-bold text-slate-800 dark:text-white">Sin resultados</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-brand-gray">
                                        {{ search ? `Ningún rol coincide con «${search}».` : 'Aún no hay roles definidos.' }}
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
    </AppShell>
</template>
