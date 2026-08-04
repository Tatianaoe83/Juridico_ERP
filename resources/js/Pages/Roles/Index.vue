<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { KeyRound, ShieldCheck, Users } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';

defineProps({
    /** [{ name, description, users_count, permissions_count, permissions, unrestricted }] */
    roles: { type: Array, required: true },
});

const breadcrumbs = [{ label: 'Inicio', href: '/calendario' }, { label: 'Roles' }];

const TONE = {
    superadmin: 'border-amber-500/40 bg-amber-500/10 text-amber-600 dark:text-amber-400',
    admin: 'border-[#459AF7]/40 bg-[#459AF7]/10 text-[#2B7FD6] dark:text-[#7CBAFA]',
};

const toneOf = (role) => TONE[role] ?? 'border-input text-muted-foreground';

const people = (n) => (n === 1 ? '1 persona' : `${n} personas`);
</script>

<template>
    <Head title="Roles" />

    <AppShell :breadcrumbs="breadcrumbs">
        <div class="mb-5 flex items-center gap-3">
            <span class="grid size-10 place-content-center rounded-lg bg-accent text-accent-foreground">
                <ShieldCheck class="size-5" />
            </span>
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Roles</h1>
                <p class="text-sm text-muted-foreground">Qué es cada rol y cuánta gente lo tiene</p>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="role in roles"
                :key="role.name"
                class="flex flex-col gap-3 rounded-xl border bg-card p-5"
            >
                <div class="flex items-start justify-between gap-3">
                    <span
                        class="rounded-full border px-2.5 py-0.5 font-mono text-[0.7rem] uppercase tracking-wider"
                        :class="toneOf(role.name)"
                    >
                        {{ role.name }}
                    </span>
                    <span class="flex shrink-0 items-center gap-1 text-xs text-muted-foreground">
                        <Users class="size-3.5" />
                        {{ people(role.users_count) }}
                    </span>
                </div>

                <p v-if="role.description" class="text-sm leading-relaxed text-muted-foreground">
                    {{ role.description }}
                </p>

                <!-- El superadmin no tiene permisos que listar: los salta en el Gate -->
                <p
                    v-if="role.unrestricted"
                    class="mt-auto rounded-lg border border-dashed px-3 py-2 text-xs text-muted-foreground"
                >
                    Sin permisos marcados.
                </p>

                <template v-else>
                    <div class="mt-auto flex flex-wrap gap-1">
                        <span
                            v-for="permission in role.permissions"
                            :key="permission"
                            class="rounded bg-muted px-1.5 py-0.5 font-mono text-[0.65rem] text-muted-foreground"
                        >
                       
                        </span>
                        <span v-if="!role.permissions.length" class="text-xs text-muted-foreground">
                            Sin permisos. Nadie con este rol puede hacer nada.
                        </span>
                    </div>

                    <Link
                        href="/permisos"
                        class="flex items-center gap-1.5 text-xs text-muted-foreground transition-colors hover:text-foreground"
                    >
                        <KeyRound class="size-3.5" />
                        Editar sus {{ role.permissions_count }} permisos
                    </Link>
                </template>
            </article>
        </div>
    </AppShell>
</template>
