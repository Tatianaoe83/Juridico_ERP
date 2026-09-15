<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye, Lock, Pencil, Plus, Search, ShieldCheck, Trash2, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
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

/* ---------- Búsqueda ---------- */

// Son pocos roles y ya vienen todos: se filtra aquí, sin ir al servidor.
const search = ref('');

const visible = computed(() => {
    const term = search.value.trim().toLowerCase();

    if (!term) return props.roles;

    return props.roles.filter((role) =>
        [role.name, roleMeta(role.name).label].some((text) => text.toLowerCase().includes(term)),
    );
});

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

/* Mismas medidas que la tabla de usuarios: responden al ancho del card. */
const TH = 'px-3 py-3 text-left text-[0.68rem] font-bold uppercase tracking-[0.14em] whitespace-nowrap tall:py-4 @2xl:px-5 @6xl:px-8';
const TD = 'px-3 py-3 tall:py-4 @2xl:px-5 @6xl:px-8';

const ACTION =
    'grid size-8 @2xl:size-9 cursor-pointer place-content-center rounded-xl transition-colors duration-150 ' +
    'focus-visible:outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-35';

const NEUTRAL =
    'text-slate-500 hover:bg-brand/[0.07] hover:text-brand focus-visible:ring-brand/25 dark:text-brand-gray dark:hover:bg-white/10 dark:hover:text-white';
</script>

<template>
    <Head title="Roles" />

    <AppShell :breadcrumbs="breadcrumbs">
        <div class="flex flex-col md:h-full md:min-h-0">
            <!-- Encabezado -->
            <div class="mb-4 flex shrink-0 flex-wrap items-center justify-between gap-4 tall:mb-6">
                <div class="flex items-center gap-3.5">
                    <span
                        class="grid size-11 place-content-center rounded-xl bg-gradient-to-br from-brand-light to-brand text-white shadow-lg shadow-brand/25 ring-1 ring-white/10 tall:size-13 tall:rounded-2xl"
                    >
                        <ShieldCheck class="size-5" />
                    </span>
                    <div>
                        <p class="text-[0.65rem] font-bold uppercase tracking-[0.2em] text-slate-400 dark:text-brand-gray/80">Administración</p>
                        <h1 class="text-2xl font-bold tracking-tight text-brand tall:text-3xl dark:text-white">Roles</h1>
                    </div>
                </div>

                <Link
                    href="/roles/crear"
                    class="inline-flex h-10 items-center gap-2 rounded-xl bg-brand px-5 text-sm font-semibold text-white shadow-lg shadow-brand/25 transition-all duration-150 hover:bg-brand/90 hover:shadow-xl hover:shadow-brand/30 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/25 active:translate-y-px tall:h-11 dark:bg-brand-light dark:shadow-black/30 dark:hover:bg-brand-light/90"
                >
                    <Plus class="size-4" />
                    Nuevo rol
                </Link>
            </div>

            <div
                class="@container flex flex-col overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_1px_3px_rgb(2_29_73/0.04),0_8px_24px_-12px_rgb(2_29_73/0.08)] md:min-h-0 md:flex-1 dark:border-white/[0.08] dark:bg-brand-deep dark:shadow-none"
            >
                <!-- Barra: búsqueda y conteo -->
                <div class="flex shrink-0 flex-wrap items-center justify-between gap-x-4 gap-y-2 px-3 py-3.5 tall:py-5 @2xl:px-5 @6xl:px-8">
                    <label class="relative w-full @xl:max-w-md">
                        <span class="sr-only">Buscar roles</span>
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Buscar rol…"
                            class="peer h-10 w-full rounded-xl border border-slate-200 bg-slate-50/70 pr-10 pl-11 text-sm text-slate-900 outline-none transition-[border-color,background-color,box-shadow] duration-150 placeholder:text-slate-400 hover:border-slate-300 focus:border-brand/50 focus:bg-white focus:ring-4 focus:ring-brand/10 tall:h-11 dark:border-white/10 dark:bg-white/[0.04] dark:text-white dark:placeholder:text-white/30 dark:hover:border-white/20 dark:focus:border-brand-gray/50 dark:focus:bg-white/[0.06] dark:focus:ring-white/10 [&::-webkit-search-cancel-button]:hidden"
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
                        <span class="font-bold text-slate-800 dark:text-white">{{ roles.length }}</span>
                        {{ roles.length === 1 ? 'rol definido' : 'roles definidos' }}
                    </p>
                </div>

                <!-- Solo desplaza si algún día hay más roles de los que caben -->
                <div class="overflow-hidden border-t border-slate-100 md:min-h-0 md:flex-1 md:overflow-y-auto dark:border-white/[0.06]">
                    <table class="w-full text-sm">
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
                            >
                                <td :class="[TD, 'w-full max-w-0']">
                                    <div class="flex items-center gap-3 @2xl:gap-4">
                                        <span
                                            class="grid size-9 shrink-0 place-content-center rounded-xl ring-1 ring-inset @2xl:size-10"
                                            :class="roleTone(role.name)"
                                        >
                                            <component :is="roleMeta(role.name).icon" class="size-4" />
                                        </span>
                                        <span class="min-w-0">
                                            <span class="flex items-center gap-2">
                                                <span class="truncate text-sm font-bold text-slate-800 @2xl:text-[0.95rem] dark:text-white">
                                                    {{ roleMeta(role.name).label }}
                                                </span>
                                                <span
                                                    v-if="role.system"
                                                    class="inline-flex shrink-0 items-center gap-1 rounded-md bg-slate-100 px-1.5 py-0.5 text-[0.6rem] font-bold tracking-wide text-slate-500 uppercase dark:bg-white/10 dark:text-brand-gray"
                                                    title="Rol del sistema: no se renombra ni se elimina"
                                                >
                                                    <Lock class="size-2.5" />
                                                    Sistema
                                                </span>
                                            </span>
                                            <code class="mt-0.5 block truncate font-mono text-xs text-slate-400 dark:text-brand-gray/80">{{ role.name }}</code>
                                            <!-- En cards angostos los conteos bajan aquí -->
                                            <span class="mt-1 block text-[0.7rem] font-semibold text-slate-500 @2xl:hidden dark:text-brand-gray">
                                                {{ people(role.users_count) }} ·
                                                {{ role.unrestricted ? 'acceso total' : `${role.permissions_count} permisos` }}
                                            </span>
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
                                        class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-bold whitespace-nowrap ring-1 ring-inset"
                                        :class="roleTone('superadmin')"
                                    >
                                        Acceso total
                                    </span>
                                    <div v-else class="w-32">
                                        <div class="flex items-baseline justify-between text-xs">
                                            <span class="font-bold text-slate-800 tabular-nums dark:text-white">{{ role.permissions_count }}</span>
                                            <span class="text-slate-400 tabular-nums dark:text-brand-gray/70">de {{ totalPermissions }}</span>
                                        </div>
                                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-white/[0.08]">
                                            <div
                                                class="h-full rounded-full bg-gradient-to-r from-brand-light to-brand transition-[width] duration-500 dark:from-brand-gray dark:to-brand-light"
                                                :style="{ width: `${totalPermissions ? (role.permissions_count / totalPermissions) * 100 : 0}%` }"
                                            />
                                        </div>
                                    </div>
                                </td>

                                <td :class="TD">
                                    <div class="flex items-center justify-end gap-0.5 @2xl:gap-1.5">
                                        <Link
                                            :href="`/roles/${role.id}`"
                                            :class="[ACTION, NEUTRAL]"
                                            :aria-label="`Ver ${roleMeta(role.name).label}`"
                                            title="Ver detalle"
                                        >
                                            <Eye class="size-[1.1rem]" />
                                        </Link>
                                        <Link
                                            v-if="role.can.update"
                                            :href="`/roles/${role.id}/editar`"
                                            :class="[ACTION, NEUTRAL]"
                                            :aria-label="`Editar ${roleMeta(role.name).label}`"
                                            title="Editar permisos"
                                        >
                                            <Pencil class="size-[1.1rem]" />
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
                                            <Trash2 class="size-[1.1rem]" />
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!visible.length">
                                <td colspan="5" class="px-6 py-10 text-center tall:py-20">
                                    <span
                                        class="mx-auto mb-3 grid size-12 place-content-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-white/[0.05] dark:text-brand-gray"
                                    >
                                        <Search class="size-6" />
                                    </span>
                                    <p class="text-base font-bold text-slate-800 dark:text-white">Sin resultados</p>
                                    <p class="mt-1 text-sm text-slate-500 dark:text-brand-gray">Ningún rol coincide con «{{ search }}».</p>
                                    <button
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
            </div>
        </div>
    </AppShell>
</template>
