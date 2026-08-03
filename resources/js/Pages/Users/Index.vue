<script setup>
import { Head, router } from '@inertiajs/vue3';
import { Search, ShieldCheck, Users } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import AppShell from '@/Layouts/AppShell.vue';
import { Input } from '@/components/ui/input';

const props = defineProps({
    users: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    /** Nombres de rol existentes, para el desplegable. */
    roles: { type: Array, default: () => [] },
    /** Cambiar roles es más sensible que editar: solo `roles.manage`. */
    canManageRoles: { type: Boolean, default: false },
});

const breadcrumbs = [{ label: 'Inicio', href: '/calendario' }, { label: 'Usuarios' }];

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

function changeRole(user, role) {
    if (role === user.roles[0]) return;

    saving.value = user.id;

    router.patch(`/usuarios/${user.id}/rol`, { role }, {
        preserveScroll: true,
        onFinish: () => (saving.value = null),
    });
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

            <div class="relative w-full max-w-64">
                <Search class="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                <Input v-model="search" class="pl-8" placeholder="Buscar por nombre o correo" />
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
                            <th v-if="canManageRoles" class="px-4 py-2.5 text-right font-medium">Cambiar rol</th>
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

                            <td v-if="canManageRoles" class="px-4 py-3 text-right">
                                <!-- Cambiarse el rol a uno mismo dejaría al sistema sin
                                     quien administre; el servidor también lo rechaza -->
                                <span v-if="user.is_self" class="text-xs text-muted-foreground">
                                    No puedes cambiar tu rol
                                </span>
                                <select
                                    v-else
                                    class="h-8 rounded-lg border border-input bg-transparent bg-none px-2.5 py-1 text-sm outline-none transition-colors focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 disabled:opacity-50 dark:bg-input/30"
                                    :disabled="saving === user.id"
                                    :value="user.roles[0] ?? ''"
                                    @change="changeRole(user, $event.target.value)"
                                >
                                    <option
                                        v-for="role in roles"
                                        :key="role"
                                        :value="role"
                                        class="bg-popover text-popover-foreground"
                                    >
                                        {{ role }}
                                    </option>
                                </select>
                            </td>
                        </tr>

                        <tr v-if="!users.data.length">
                            <td colspan="4" class="px-4 py-12 text-center text-sm text-muted-foreground">
                                Ninguna cuenta coincide con la búsqueda.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <p v-if="users.meta.last_page > 1" class="mt-3 text-xs text-muted-foreground">
            Página {{ users.meta.current_page }} de {{ users.meta.last_page }}
        </p>
    </AppShell>
</template>
