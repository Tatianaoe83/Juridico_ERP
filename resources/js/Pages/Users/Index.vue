<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    Eye,
    Pencil,
    Search,
    ShieldCheck,
    Trash2,
    UserPlus,
    Users,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';
import AppShell from '@/Layouts/AppShell.vue';
import UserFormDialog from '@/components/app/UserFormDialog.vue';
import { Input } from '@/components/ui/input';
import { useSwal } from '@/composables/useSwal';
import Button from '@/components/ui/button/Button.vue';

const props = defineProps({
    users: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    /** Nombres de rol existentes, para el desplegable. */
    roles: { type: Array, default: () => [] },
    /** Cambiar roles es más sensible que editar: solo `roles.manage`. */
    canManageRoles: { type: Boolean, default: false },
    canCreateUsers: { type: Boolean, default: false },
});

/** Diálogo de alta. */
const creating = ref(false);

/** La búsqueda vigente viaja con la página: sin esto, pasar de página la pierde. */
function goTo(page) {
    router.get('/usuarios', { ...(search.value ? { search: search.value } : {}), page }, {
        preserveState: true,
        preserveScroll: true,
    });
}

const breadcrumbs = [{ label: 'Inicio', href: '/calendario' }, { label: 'Usuarios' }];

const { confirm, confirmDelete, blocks } = useSwal();

/* ---------- Búsqueda ---------- */

const search = ref(props.filters.search ?? '');

let timer;

// Se espera a que deje de teclear: una petición por letra satura el servidor
// y hace parpadear la tabla.
watch(search, (value) => {
    clearTimeout(timer);

    timer = setTimeout(() => {
        router.get('/usuarios', value ? { search: value } : {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, 350);
});

/* ---------- Rol ---------- */

const saving = ref(null);

/**
 * @param select  El propio <select>: si se cancela hay que devolverlo al rol
 *                anterior. El navegador ya cambió lo que muestra, y como la
 *                página no se recarga se quedaría mintiendo.
 */
async function changeRole(user, role, select) {
    const current = user.roles[0] ?? 'sin rol';

    if (role === user.roles[0]) return;

    // Promover a superadmin concede acceso total y salta toda comprobación:
    // merece el aviso rojo, no el neutro.
    const promoting = role === 'superadmin';

    const ask = promoting ? confirmDelete : confirm;

    const { lead, chip, arrow, panel, note, stack } = blocks;

    const ok = await ask({
        title: promoting ? '¿Conceder acceso total?' : '¿Cambiar el rol?',
        html: stack(
            lead(
                `<span class="font-medium">${user.name}</span><br>` +
                    `<span class="mt-1.5 inline-block">${chip(current)}${arrow()}${chip(role)}</span>`,
            ),
            promoting
                ? panel({
                    label: 'Qué implica',
                    tone: 'danger',
                    items: [
                        'Salta toda comprobación de permisos',
                        'Puede repartir roles, incluido el tuyo',
                        'Puede eliminar cualquier cuenta menos la propia',
                    ],
                })
                : panel({
                    label: 'Qué cambia',
                    items: [
                        `Su menú y sus permisos pasan a los de <b>${role}</b>`,
                        'Aplica de inmediato; si tiene sesión abierta, al recargar',
                    ],
                }),
            note('Puedes revertirlo desde esta misma pantalla.'),
        ),
        confirmText: promoting ? 'Conceder' : 'Cambiar rol',
    });

    if (!ok) {
        select.value = user.roles[0] ?? '';

        return;
    }

    saving.value = user.id;

    router.patch(`/usuarios/${user.id}/rol`, { role }, {
        preserveScroll: true,
        onError: () => (select.value = user.roles[0] ?? ''),
        onFinish: () => (saving.value = null),
    });
}

/* ---------- Baja ---------- */

async function remove(user) {
    const { lead, chip, panel, note, stack } = blocks;

    const ok = await confirmDelete({
        title: '¿Eliminar esta cuenta?',
        html: stack(
            lead(
                `<span class="font-medium">${user.name}</span><br>` +
                    `<span class="mt-1.5 inline-block">${chip(user.email)}</span>`,
            ),
            panel({
                label: 'Qué se pierde',
                tone: 'danger',
                items: [
                    'La cuenta y su acceso al sistema',
                    'Sus tokens de API y la vinculación con Microsoft',
                    'No se puede deshacer',
                ],
            }),
            note('Sus eventos siguen en Outlook: esto solo borra la cuenta de esta app.'),
        ),
        confirmText: 'Eliminar cuenta',
    });

    if (!ok) return;

    router.delete(`/usuarios/${user.id}`, { preserveScroll: true });
}

/** El superadmin no lleva permisos marcados: los salta con Gate::before. */
const TONE = {
    superadmin: 'border-amber-500/40 bg-amber-500/10 text-amber-600 dark:text-amber-400',
    admin: 'border-[#459AF7]/40 bg-[#459AF7]/10 text-[#2B7FD6] dark:text-[#7CBAFA]',
};

const toneOf = (role) => TONE[role] ?? 'border-input text-muted-foreground';

function initials(name) {
    return name
        .split(' ')
        .slice(0, 2)
        .map((part) => part[0] ?? '')
        .join('')
        .toUpperCase();
}

function joined(iso) {
    return iso ? new Date(iso).toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric' }) : '';
}
</script>

<template>
    <Head title="Usuarios" />

    <AppShell :breadcrumbs="breadcrumbs">
        <div class="mb-5 flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="grid size-10 place-content-center rounded-lg bg-accent text-accent-foreground">
                    <Users class="size-5" />
                </span>
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Usuarios</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ users.meta.total }}
                        {{ users.meta.total === 1 ? 'cuenta registrada' : 'cuentas registradas' }}
                    </p>
                </div>
            </div>
            <div class="flex w-full items-center gap-2 sm:w-auto">
                <div class="relative w-full max-w-64">
                    <Search class="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" class="pl-8" placeholder="Buscar por nombre o correo" />
                </div>
                <Button v-if="canCreateUsers" class="shrink-0 gap-1.5" @click="creating = true">
                    <UserPlus class="size-4" />
                    Nuevo usuario
                </Button>
            </div>
        </div>

        <div v-if="!canManageRoles" class="mb-4 flex items-start gap-2.5 rounded-lg border bg-muted/30 px-3.5 py-3 text-sm">
            <ShieldCheck class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
            <p class="text-muted-foreground">
                Puedes ver las cuentas, pero no cambiar roles. Eso requiere el permiso
                <code class="rounded bg-muted px-1 py-0.5 text-xs">roles.manage</code>.
            </p>
        </div>

        <div class="overflow-hidden rounded-xl border bg-card">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/40 text-xs text-muted-foreground">
                            <th class="px-4 py-2.5 text-left font-medium">Persona</th>
                            <th class="px-4 py-2.5 text-left font-medium">Rol</th>
                            <th class="hidden px-4 py-2.5 text-left font-medium sm:table-cell">Alta</th>
                            <!-- `w-px` encoge la columna a su contenido: sin esto la
                                 tabla la estira y los iconos quedan lejísimos -->
                            <th class="w-px whitespace-nowrap px-4 py-2.5 text-right font-medium">Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="user in users.data" :key="user.id" class="border-b last:border-b-0 hover:bg-accent/40">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="grid size-8 shrink-0 place-content-center rounded-full bg-muted text-xs font-medium">
                                        {{ initials(user.name) }}
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block truncate font-medium">
                                            {{ user.name }}
                                            <span v-if="user.is_self" class="text-xs font-normal text-muted-foreground">· tú</span>
                                        </span>
                                        <span class="block truncate text-xs text-muted-foreground">{{ user.email }}</span>
                                    </span>
                                </div>
                            </td>

                            <td class="px-4 py-3">
                                <span
                                    v-for="role in user.roles"
                                    :key="role"
                                    class="mr-1 inline-block rounded-full border px-2 py-0.5 font-mono text-[0.65rem] uppercase tracking-wide"
                                    :class="toneOf(role)"
                                >
                                    {{ role }}
                                </span>
                                <span v-if="!user.roles.length" class="text-xs text-muted-foreground">Sin rol</span>
                            </td>

                            <td class="hidden px-4 py-3 text-xs text-muted-foreground sm:table-cell">
                                {{ joined(user.created_at) }}
                            </td>

                            

                            <td class="w-px whitespace-nowrap px-4 py-3">
                                <!-- Solo icono: el nombre de la acción va en `title` y en
                                     `sr-only`, para que siga anunciándose en lectores -->
                                <div class="flex items-center justify-end gap-0.5">
                                    <Button variant="ghost" size="icon-sm" title="Ver ficha" as-child>
                                        <Link :href="`/usuarios/${user.id}`">
                                            <Eye class="size-4" />
                                            <span class="sr-only">Ver ficha de {{ user.name }}</span>
                                        </Link>
                                    </Button>

                                    <Button
                                        v-if="user.can.update"
                                        variant="ghost"
                                        size="icon-sm"
                                        title="Editar"
                                        as-child
                                    >
                                        <Link :href="`/usuarios/${user.id}/editar`">
                                            <Pencil class="size-4" />
                                            <span class="sr-only">Editar a {{ user.name }}</span>
                                        </Link>
                                    </Button>

                                    <Button
                                        v-if="user.can.delete"
                                        variant="ghost"
                                        size="icon-sm"
                                        title="Eliminar"
                                        class="text-destructive hover:text-destructive"
                                        @click="remove(user)"
                                    >
                                        <Trash2 class="size-4" />
                                        <span class="sr-only">Eliminar a {{ user.name }}</span>
                                    </Button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="!users.data.length">
                            <td :colspan="canManageRoles ? 5 : 4" class="px-4 py-12 text-center text-sm text-muted-foreground">
                                Ninguna cuenta coincide con la búsqueda.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="users.meta.last_page > 1" class="mt-3 flex items-center justify-between gap-4">
            <p class="text-xs text-muted-foreground">
                Página {{ users.meta.current_page }} de {{ users.meta.last_page }}
                · {{ users.meta.total }} cuentas
            </p>

            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    class="gap-1"
                    :disabled="users.meta.current_page <= 1"
                    @click="goTo(users.meta.current_page - 1)"
                >
                    <ChevronLeft class="size-4" />
                    Anterior
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    class="gap-1"
                    :disabled="users.meta.current_page >= users.meta.last_page"
                    @click="goTo(users.meta.current_page + 1)"
                >
                    Siguiente
                    <ChevronRight class="size-4" />
                </Button>
            </div>
        </div>

        <UserFormDialog
            v-if="canCreateUsers"
            v-model:open="creating"
            :roles="roles"
            :can-manage-roles="canManageRoles"
        />
    </AppShell>
</template>
