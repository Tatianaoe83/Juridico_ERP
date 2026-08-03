<script setup>
import { Head, router } from '@inertiajs/vue3';
import { Info, KeyRound } from 'lucide-vue-next';
import { ref } from 'vue';
import AppShell from '@/Layouts/AppShell.vue';

const props = defineProps({
    /** [{ area, permissions: [] }] — agrupado por prefijo del permiso. */
    groups: { type: Array, required: true },
    /** [{ name, permissions: [], unrestricted }] */
    roles: { type: Array, required: true },
});

const breadcrumbs = [{ label: 'Inicio', href: '/calendario' }, { label: 'Permisos' }];

/**
 * Copia local para que el interruptor responda al instante.
 *
 * Sin esto la casilla se queda quieta hasta que vuelve el servidor y parece
 * que el clic no hizo nada. Si la petición falla, se revierte.
 */
const granted = ref(
    Object.fromEntries(props.roles.map((role) => [role.name, new Set(role.permissions)])),
);

const saving = ref(null);

const has = (role, permission) => granted.value[role]?.has(permission) ?? false;

function toggle(role, permission) {
    const set = granted.value[role];
    const next = !set.has(permission);

    next ? set.add(permission) : set.delete(permission);
    saving.value = `${role}:${permission}`;

    router.patch(
        '/permisos',
        { role, permission, granted: next },
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => (next ? set.delete(permission) : set.add(permission)),
            onFinish: () => (saving.value = null),
        },
    );
}

const TONE = {
    superadmin: 'text-amber-600 dark:text-amber-400',
    admin: 'text-[#2B7FD6] dark:text-[#7CBAFA]',
};
</script>

<template>
    <Head title="Permisos" />

    <AppShell :breadcrumbs="breadcrumbs">
        <div class="mb-5 flex items-center gap-3">
            <span class="grid size-10 place-content-center rounded-lg bg-accent text-accent-foreground">
                <KeyRound class="size-5" />
            </span>
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Permisos</h1>
                <p class="text-sm text-muted-foreground">Qué puede hacer cada rol</p>
            </div>
        </div>

        <div class="mb-3 flex max-w-3xl items-start gap-2 rounded-lg border bg-muted/30 px-3 py-2 text-xs">
            <Info class="mt-0.5 size-3.5 shrink-0 text-muted-foreground" />
            <p class="text-muted-foreground">
                Los cambios aplican de inmediato a todas las personas con ese rol. El
                <span class="font-medium text-foreground">superadmin</span> no lleva casillas: pasa
                por el Gate y hereda cualquier permiso que se agregue después.
            </p>
        </div>

        <div class="max-w-3xl overflow-hidden rounded-xl border bg-card">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/40 text-xs text-muted-foreground">
                            <th class="px-3 py-2 text-left font-medium">Permiso</th>
                            <th
                                v-for="role in roles"
                                :key="role.name"
                                class="w-24 px-2 py-2 text-center font-mono text-[0.6rem] uppercase tracking-wider"
                                :class="TONE[role.name]"
                            >
                                {{ role.name }}
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <template v-for="group in groups" :key="group.area">
                            <tr class="border-b bg-muted/20">
                                <td
                                    :colspan="roles.length + 1"
                                    class="px-3 py-1 font-mono text-[0.55rem] uppercase tracking-widest text-muted-foreground"
                                >
                                    {{ group.area }}
                                </td>
                            </tr>

                            <tr
                                v-for="permission in group.permissions"
                                :key="permission"
                                class="border-b last:border-b-0 hover:bg-accent/40"
                            >
                                <td class="px-3 py-1.5 font-mono text-[0.7rem]">{{ permission }}</td>

                                <td v-for="role in roles" :key="role.name" class="px-2 py-1.5 text-center">
                                    <span
                                        v-if="role.unrestricted"
                                        class="font-mono text-[0.65rem] text-amber-600 dark:text-amber-400"
                                        title="Pasa por Gate::before, sin permisos marcados"
                                    >
                                        todo
                                    </span>

                                    <button
                                        v-else
                                        type="button"
                                        role="switch"
                                        :aria-checked="has(role.name, permission)"
                                        :aria-label="`${permission} para ${role.name}`"
                                        :disabled="saving === `${role.name}:${permission}`"
                                        class="relative inline-flex h-[18px] w-8 shrink-0 items-center rounded-full transition-colors disabled:opacity-50"
                                        :class="has(role.name, permission) ? 'bg-[#459AF7]' : 'bg-muted-foreground/30'"
                                        @click="toggle(role.name, permission)"
                                    >
                                        <span
                                            class="size-3.5 rounded-full bg-white shadow transition-transform motion-reduce:transition-none"
                                            :class="has(role.name, permission) ? 'translate-x-[15px]' : 'translate-x-0.5'"
                                        />
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </AppShell>
</template>
