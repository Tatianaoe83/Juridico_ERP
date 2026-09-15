<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Check, Infinity as InfinityIcon, KeyRound, Minus, Pencil, Trash2, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppShell from '@/Layouts/AppShell.vue';
import ConfirmDeleteDialog from '@/components/app/ConfirmDeleteDialog.vue';
import { roleMeta, roleTone, userInitials } from '@/lib/users';

const props = defineProps({
    /** { id, name, users_count, permissions_count, unrestricted, can, permissions, users } */
    role: { type: Object, required: true },
    /** [{ key, area, permissions: [{ name, label }] }] */
    groups: { type: Array, required: true },
});

const meta = computed(() => roleMeta(props.role.name));

const breadcrumbs = computed(() => [
    { label: 'Inicio', href: '/calendario' },
    { label: 'Roles', href: '/roles' },
    { label: meta.value.label },
]);

const granted = computed(() => new Set(props.role.permissions));

const total = computed(() => props.groups.reduce((sum, group) => sum + group.permissions.length, 0));

/** El superadmin no tiene permisos marcados pero los tiene todos por el Gate. */
const has = (name) => props.role.unrestricted || granted.value.has(name);

const countIn = (group) => group.permissions.filter((p) => has(p.name)).length;

const joined = computed(() =>
    props.role.created_at
        ? new Date(props.role.created_at).toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' })
        : '—',
);

/* ---------- Eliminar ---------- */

const deleteOpen = ref(false);
const deleting = ref(false);

function destroy() {
    deleting.value = true;
    router.delete(`/roles/${props.role.id}`, { onFinish: () => (deleting.value = false) });
}

const CARD =
    'rounded-2xl border border-slate-200/80 bg-white shadow-[0_1px_3px_rgb(2_29_73/0.04),0_8px_24px_-12px_rgb(2_29_73/0.08)] ' +
    'dark:border-white/[0.08] dark:bg-brand-deep dark:shadow-none';

const LABEL = 'flex items-center gap-2 text-[0.68rem] font-bold uppercase tracking-[0.16em] text-slate-400 dark:text-brand-gray/80';
</script>

<template>
    <Head :title="meta.label" />

    <AppShell :breadcrumbs="breadcrumbs">
        <div class="flex flex-col lg:h-full lg:min-h-0">
            <!-- Encabezado -->
            <div class="mb-4 flex shrink-0 flex-wrap items-center justify-between gap-4 tall:mb-6">
                <div class="flex min-w-0 items-center gap-3.5">
                    <Link
                        href="/roles"
                        class="grid size-10 shrink-0 place-content-center rounded-xl border border-slate-200 bg-white text-slate-500 transition-colors hover:border-slate-300 hover:text-brand focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/15 dark:border-white/10 dark:bg-white/[0.03] dark:text-brand-gray dark:hover:text-white"
                        aria-label="Volver a roles"
                    >
                        <ArrowLeft class="size-4" />
                    </Link>
                    <span class="grid size-11 shrink-0 place-content-center rounded-xl ring-1 ring-inset tall:size-12" :class="roleTone(role.name)">
                        <component :is="meta.icon" class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="flex items-center gap-2 text-[0.65rem] font-bold uppercase tracking-[0.2em] text-slate-400 dark:text-brand-gray/80">
                            <code class="font-mono normal-case tracking-normal">{{ role.name }}</code>
                        </p>
                        <h1 class="truncate text-2xl font-bold tracking-tight text-brand tall:text-3xl dark:text-white">{{ meta.label }}</h1>
                    </div>
                </div>

                <div class="flex items-center gap-2.5">
                    <button
                        v-if="role.can.delete"
                        type="button"
                        class="inline-flex h-10 cursor-pointer items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-600 transition-colors hover:border-red-200 hover:bg-red-50 hover:text-red-600 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-500/15 disabled:opacity-50 tall:h-11 dark:border-white/10 dark:bg-transparent dark:text-brand-gray dark:hover:border-red-400/30 dark:hover:bg-red-500/10 dark:hover:text-red-300"
                        :disabled="deleting"
                        @click="deleteOpen = true"
                    >
                        <Trash2 class="size-4" />
                        Eliminar
                    </button>
                    <Link
                        v-if="role.can.update"
                        :href="`/roles/${role.id}/editar`"
                        class="inline-flex h-10 items-center gap-2 rounded-xl bg-brand px-5 text-sm font-semibold text-white shadow-lg shadow-brand/25 transition-all duration-150 hover:bg-brand/90 hover:shadow-xl hover:shadow-brand/30 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/25 active:translate-y-px tall:h-11 dark:bg-brand-light dark:shadow-black/30 dark:hover:bg-brand-light/90"
                    >
                        <Pencil class="size-4" />
                        Editar permisos
                    </Link>
                </div>
            </div>

            <div class="grid gap-4 lg:min-h-0 lg:flex-1 lg:grid-cols-[19rem_minmax(0,1fr)] tall:gap-5">
                <!-- Ficha del rol -->
                <aside class="flex flex-col gap-4 lg:min-h-0 tall:gap-5">
                    <section :class="[CARD, 'p-5']">
                        <dl class="grid grid-cols-2 gap-3">
                            <div class="rounded-xl border border-slate-200/80 bg-slate-50/60 p-3.5 dark:border-white/[0.08] dark:bg-white/[0.03]">
                                <dt :class="LABEL"><Users class="size-3.5" /> Usuarios</dt>
                                <dd class="mt-1.5 text-2xl font-bold text-brand tabular-nums dark:text-white">{{ role.users_count }}</dd>
                            </div>
                            <div class="rounded-xl border border-slate-200/80 bg-slate-50/60 p-3.5 dark:border-white/[0.08] dark:bg-white/[0.03]">
                                <dt :class="LABEL"><KeyRound class="size-3.5" /> Permisos</dt>
                                <dd class="mt-1.5 flex items-baseline gap-1 text-2xl font-bold text-brand tabular-nums dark:text-white">
                                    <template v-if="role.unrestricted"><InfinityIcon class="size-6 self-center" /></template>
                                    <template v-else>
                                        {{ role.permissions_count }}
                                        <span class="text-xs font-semibold text-slate-400 dark:text-brand-gray/70">/{{ total }}</span>
                                    </template>
                                </dd>
                            </div>
                        </dl>
                        <p class="mt-3 text-xs text-slate-500 dark:text-brand-gray">Creado el {{ joined }}</p>
                    </section>

                    <!-- A quién afecta: una muestra de quienes lo tienen -->
                    <section :class="[CARD, 'flex flex-col overflow-hidden lg:min-h-0 lg:flex-1']">
                        <h2 :class="[LABEL, 'shrink-0 px-5 pt-5 pb-3']">
                            <Users class="size-3.5" />
                            Personas con este rol
                        </h2>
                        <ul v-if="role.users.length" class="min-h-0 flex-1 space-y-1 overflow-y-auto px-3 pb-3">
                            <li v-for="person in role.users" :key="person.id" class="flex items-center gap-3 rounded-lg px-2 py-2">
                                <span
                                    class="grid size-8 shrink-0 place-content-center rounded-lg bg-gradient-to-br from-brand-light to-brand text-[0.65rem] font-bold text-white"
                                >
                                    {{ userInitials(person.name) }}
                                </span>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-semibold text-slate-800 dark:text-white">{{ person.name }}</span>
                                    <span class="block truncate text-xs text-slate-500 dark:text-brand-gray">{{ person.email }}</span>
                                </span>
                            </li>
                            <li v-if="role.users_count > role.users.length" class="px-2 pt-1 text-xs font-semibold text-slate-500 dark:text-brand-gray">
                                y {{ role.users_count - role.users.length }} más
                            </li>
                        </ul>
                        <p v-else class="px-5 pb-5 text-sm text-slate-500 dark:text-brand-gray">Nadie tiene este rol todavía.</p>
                    </section>
                </aside>

                <!-- Permisos del rol, por área -->
                <section :class="[CARD, '@container flex flex-col overflow-hidden lg:min-h-0']">
                    <div class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]">
                        <div>
                            <h2 class="text-base font-bold text-brand dark:text-white">Permisos asignados</h2>
                            <p class="text-xs text-slate-500 dark:text-brand-gray">Lo que puede hacer quien tenga este rol.</p>
                        </div>
                    </div>

                    <div
                        v-if="role.unrestricted"
                        class="mx-4 mt-4 flex shrink-0 gap-3 rounded-xl border border-amber-300/60 bg-amber-50 px-4 py-3 tall:mx-5 tall:mt-5 dark:border-amber-400/25 dark:bg-amber-400/10"
                    >
                        <InfinityIcon class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-300" />
                        <p class="text-xs leading-relaxed text-amber-800 dark:text-amber-200/90">
                            <span class="font-bold">Acceso total.</span>
                            No lleva permisos marcados: pasa por el Gate y hereda cualquiera que se agregue después.
                        </p>
                    </div>

                    <div class="grid content-start gap-3 overflow-y-auto p-4 lg:min-h-0 lg:flex-1 @3xl:grid-cols-2 tall:gap-4 tall:p-5">
                        <div
                            v-for="group in groups"
                            :key="group.key"
                            class="overflow-hidden rounded-xl border border-slate-200/80 dark:border-white/[0.08]"
                        >
                            <div
                                class="flex items-center gap-3 border-b border-slate-100 bg-slate-50/80 px-4 py-2.5 dark:border-white/[0.06] dark:bg-white/[0.03]"
                            >
                                <span class="flex-1 text-[0.7rem] font-bold uppercase tracking-[0.14em] text-slate-600 dark:text-slate-200">{{ group.area }}</span>
                                <span
                                    class="rounded-md px-1.5 py-0.5 text-[0.65rem] font-bold tabular-nums"
                                    :class="countIn(group) ? 'bg-brand/[0.08] text-brand dark:bg-white/10 dark:text-white' : 'text-slate-400 dark:text-brand-gray/70'"
                                >
                                    {{ countIn(group) }}/{{ group.permissions.length }}
                                </span>
                            </div>

                            <ul class="divide-y divide-slate-100 dark:divide-white/[0.05]">
                                <li v-for="permission in group.permissions" :key="permission.name" class="flex items-center gap-3 px-4 py-2.5 tall:py-3">
                                    <!-- Icono y texto: el estado no depende solo del color -->
                                    <span
                                        class="grid size-5 shrink-0 place-content-center rounded-full"
                                        :class="
                                            has(permission.name)
                                                ? role.unrestricted
                                                    ? 'bg-amber-500 text-white dark:bg-amber-400 dark:text-brand-deep'
                                                    : 'bg-brand text-white dark:bg-brand-light'
                                                : 'bg-slate-100 text-slate-400 dark:bg-white/[0.06] dark:text-white/30'
                                        "
                                    >
                                        <Check v-if="has(permission.name)" class="size-3" stroke-width="3" />
                                        <Minus v-else class="size-3" stroke-width="3" />
                                    </span>
                                    <code
                                        class="min-w-0 flex-1 truncate font-mono text-[0.8rem]"
                                        :class="has(permission.name) ? 'font-semibold text-slate-800 dark:text-white' : 'text-slate-400 dark:text-brand-gray/70'"
                                    >
                                        {{ permission.name }}
                                        <span class="sr-only">{{ has(permission.name) ? '(concedido)' : '(no concedido)' }}</span>
                                    </code>
                                </li>
                            </ul>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <ConfirmDeleteDialog
            v-model:open="deleteOpen"
            :text="`¿Seguro que quieres eliminar el rol «${meta.label}»? Se pierden sus permisos asignados.`"
            @confirm="destroy"
        />
    </AppShell>
</template>
