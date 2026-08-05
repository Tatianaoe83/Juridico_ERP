<script setup>
import { Head, router } from '@inertiajs/vue3';
import { Check, Info, KeyRound, Lock } from 'lucide-vue-next';
import { ref } from 'vue';
import AppShell from '@/Layouts/AppShell.vue';

const props = defineProps({
    /** [{ area, permissions: [] }] — agrupado por prefijo del permiso. */
    groups: { type: Array, required: true },
    /** [{ name, permissions: [], unrestricted, own }] */
    roles: { type: Array, required: true },
    /** Permisos que ningún rol puede perder. */
    locked: { type: Array, default: () => [] },
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

/**
 * Un interruptor se bloquea por dos motivos distintos, y conviene distinguirlos
 * en el aviso: el rol propio no se toca nunca, y un permiso base solo se puede
 * conceder, no retirar.
 */
function lockReason(role, permission) {
    if (role.own) {
        return 'Es tu propio rol: concederte permisos aquí sería saltarte el control.';
    }

    if (props.locked.includes(permission) && has(role.name, permission)) {
        return 'Permiso base: sin él, ese rol no puede abrir ninguna pantalla.';
    }

    return null;
}

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

        <div class="mb-3  flex max-w-3xl items-start gap-2 rounded-lg border bg-muted/30 px-3 py-2 text-xs">
            <Info class="mt-0.5 size-3.5 shrink-0 text-muted-foreground" />
            <p class="text-muted-foreground">
                Los cambios se aplican de inmediato a todas las personas con ese rol.
            </p>
        </div>

        <!-- Una tarjeta por área en vez de una tabla larga: el encabezado de
             cada bloque (roles y sus colores) queda cerca de lo que describe,
             sin tener que subir la vista para recordar qué columna es cuál -->
        <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
            <section v-for="group in groups" :key="group.area" class="overflow-hidden rounded-xl border bg-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-muted/40 text-xs text-muted-foreground">
                                <th class="px-3 py-2 text-left font-mono font-medium uppercase tracking-widest">
                                    {{ group.area }}
                                </th>
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

                                    <span
                                        v-else-if="lockReason(role, permission)"
                                        class="inline-flex items-center gap-1 text-muted-foreground opacity-50"
                                        :title="lockReason(role, permission)"
                                    >
                                        <Lock class="size-3" />
                                        <span
                                            class="pointer-events-none inline-flex size-4 items-center justify-center rounded border"
                                            :class="has(role.name, permission)
                                                ? 'border-[#459AF7] bg-[#459AF7] text-white'
                                                : 'border-input'"
                                        >
                                            <Check v-if="has(role.name, permission)" class="size-3" stroke-width="3" />
                                        </span>
                                    </span>

                                    <input
                                        v-else
                                        type="checkbox"
                                        :checked="has(role.name, permission)"
                                        :aria-label="`${permission} para ${role.name}`"
                                        :disabled="saving === `${role.name}:${permission}`"
                                        class="size-4 rounded border-input accent-[#459AF7] disabled:opacity-50"
                                        @change="toggle(role.name, permission)"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppShell>
</template>
