<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, KeyRound, Loader2, Lock, Save, ShieldPlus, Tag, TriangleAlert } from 'lucide-vue-next';
import { computed } from 'vue';
import AppShell from '@/Layouts/AppShell.vue';
import { roleMeta } from '@/lib/users';

const props = defineProps({
    /** null = alta · { id, name, system, permissions: [] } = edición. */
    role: { type: Object, default: null },
    /** [{ key, area, permissions: [{ name, label }] }] — solo los que existen. */
    groups: { type: Array, required: true },
});

const editing = computed(() => props.role !== null);

const title = computed(() => (editing.value ? `Editar ${roleMeta(props.role.name).label}` : 'Nuevo rol'));

const breadcrumbs = computed(() => [
    { label: 'Inicio', href: '/calendario' },
    { label: 'Roles', href: '/roles' },
    ...(editing.value ? [{ label: roleMeta(props.role.name).label, href: `/roles/${props.role.id}` }] : []),
    { label: editing.value ? 'Editar' : 'Nuevo' },
]);

const form = useForm({
    name: props.role?.name ?? '',
    permissions: [...(props.role?.permissions ?? [])],
});

const all = computed(() => props.groups.flatMap((group) => group.permissions.map((p) => p.name)));

const selected = computed(() => new Set(form.permissions));

/* ---------- Selección ---------- */

function toggle(name) {
    form.permissions = selected.value.has(name)
        ? form.permissions.filter((p) => p !== name)
        : [...form.permissions, name];
}

function countIn(group) {
    return group.permissions.filter((p) => selected.value.has(p.name)).length;
}

/** Marca o desmarca el área entera; si estaba a medias, la completa. */
function toggleGroup(group) {
    const names = group.permissions.map((p) => p.name);
    const full = countIn(group) === names.length;

    form.permissions = full
        ? form.permissions.filter((p) => !names.includes(p))
        : [...new Set([...form.permissions, ...names])];
}

function selectAll(on) {
    form.permissions = on ? [...all.value] : [];
}

const progress = computed(() => (all.value.length ? (form.permissions.length / all.value.length) * 100 : 0));

// Quien lo tiene puede concederse cualquier otro permiso: merece el aviso.
const sensitive = computed(() => selected.value.has('roles.manage'));

/* ---------- Guardar ---------- */

function submit() {
    // El nombre de un rol del sistema no viaja: el servidor lo rechaza.
    form.transform((data) => (props.role?.system ? { permissions: data.permissions } : data));

    editing.value ? form.patch(`/roles/${props.role.id}`) : form.post('/roles');
}

const cancelHref = computed(() => (editing.value ? `/roles/${props.role.id}` : '/roles'));

const CARD =
    'rounded-2xl border border-slate-200/80 bg-white shadow-[0_1px_3px_rgb(2_29_73/0.04),0_8px_24px_-12px_rgb(2_29_73/0.08)] ' +
    'dark:border-white/[0.08] dark:bg-brand-deep dark:shadow-none';

// El bg-white le gana al fondo que pone @tailwindcss/forms al marcarla y la
// palomita (blanca) quedaba invisible: el fondo marcado va explícito.
const CHECK =
    'size-[1.05rem] shrink-0 cursor-pointer rounded-[0.3rem] border-slate-300 bg-white text-brand transition-colors ' +
    'checked:border-transparent checked:bg-brand indeterminate:border-transparent indeterminate:bg-brand ' +
    'focus:ring-2 focus:ring-brand/25 focus:ring-offset-0 ' +
    'dark:border-white/25 dark:bg-white/[0.04] dark:text-brand-light dark:checked:bg-brand-light dark:indeterminate:bg-brand-light dark:focus:ring-white/20';
</script>

<template>
    <Head :title="title" />

    <AppShell :breadcrumbs="breadcrumbs">
        <form class="flex flex-col lg:h-full lg:min-h-0" novalidate @submit.prevent="submit">
            <!-- Encabezado -->
            <div class="mb-4 flex shrink-0 flex-wrap items-center justify-between gap-4 tall:mb-6">
                <div class="flex min-w-0 items-center gap-3.5">
                    <Link
                        :href="cancelHref"
                        class="grid size-10 shrink-0 place-content-center rounded-xl border border-slate-200 bg-white text-slate-500 transition-colors hover:border-slate-300 hover:text-brand focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/15 dark:border-white/10 dark:bg-white/[0.03] dark:text-brand-gray dark:hover:text-white"
                        aria-label="Volver"
                    >
                        <ArrowLeft class="size-4" />
                    </Link>
                    <div class="min-w-0">
                        <p class="text-[0.65rem] font-bold uppercase tracking-[0.2em] text-slate-400 dark:text-brand-gray/80">
                            {{ editing ? 'Editar rol' : 'Roles' }}
                        </p>
                        <h1 class="truncate text-2xl font-bold tracking-tight text-brand tall:text-3xl dark:text-white">{{ title }}</h1>
                    </div>
                </div>

                <div class="flex items-center gap-2.5">
                    <Link
                        :href="cancelHref"
                        class="inline-flex h-10 items-center rounded-xl border border-slate-200 bg-white px-5 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-500/10 tall:h-11 dark:border-white/10 dark:bg-transparent dark:text-brand-gray dark:hover:bg-white/[0.06] dark:hover:text-white"
                    >
                        Cancelar
                    </Link>
                    <button
                        type="submit"
                        class="inline-flex h-10 cursor-pointer items-center gap-2 rounded-xl bg-brand px-5 text-sm font-semibold text-white shadow-lg shadow-brand/25 transition-all duration-150 hover:bg-brand/90 hover:shadow-xl hover:shadow-brand/30 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/25 active:translate-y-px disabled:pointer-events-none disabled:opacity-60 tall:h-11 dark:bg-brand-light dark:shadow-black/30 dark:hover:bg-brand-light/90"
                        :disabled="form.processing"
                    >
                        <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                        <Save v-else-if="editing" class="size-4" />
                        <ShieldPlus v-else class="size-4" />
                        {{ editing ? 'Guardar cambios' : 'Crear rol' }}
                    </button>
                </div>
            </div>

            <div class="grid gap-4 lg:min-h-0 lg:flex-1 lg:grid-cols-[19rem_minmax(0,1fr)] tall:gap-5">
                <!-- Datos del rol -->
                <aside class="flex flex-col gap-4 lg:min-h-0 tall:gap-5">
                    <section :class="[CARD, 'p-5']">
                        <h2 class="flex items-center gap-2 text-[0.68rem] font-bold uppercase tracking-[0.16em] text-slate-400 dark:text-brand-gray/80">
                            <Tag class="size-3.5" />
                            Datos del rol
                        </h2>

                        <label for="role-name" class="mt-4 mb-1.5 block text-[0.8rem] font-semibold text-slate-700 dark:text-slate-200">
                            Nombre
                        </label>
                        <div class="relative">
                            <input
                                id="role-name"
                                v-model="form.name"
                                type="text"
                                required
                                autofocus
                                autocomplete="off"
                                placeholder="ej. abogado_senior"
                                :readonly="role?.system"
                                :aria-invalid="Boolean(form.errors.name)"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-slate-50/60 px-3.5 font-mono text-sm text-slate-900 outline-none transition-[border-color,background-color,box-shadow] duration-150 placeholder:font-sans placeholder:text-slate-400 hover:border-slate-300 focus:border-brand/60 focus:bg-white focus:ring-4 focus:ring-brand/10 read-only:cursor-not-allowed read-only:bg-slate-100 read-only:text-slate-500 read-only:hover:border-slate-200 read-only:focus:ring-0 aria-invalid:border-red-400 dark:border-white/10 dark:bg-white/[0.04] dark:text-white dark:read-only:bg-white/[0.02] dark:read-only:text-brand-gray dark:focus:border-brand-gray/60 dark:focus:ring-white/10"
                                :class="role?.system && 'pr-10'"
                            />
                            <Lock
                                v-if="role?.system"
                                class="pointer-events-none absolute top-1/2 right-3.5 size-4 -translate-y-1/2 text-slate-400"
                            />
                        </div>
                        <p v-if="form.errors.name" class="mt-1.5 text-xs font-medium text-red-600 dark:text-red-400">{{ form.errors.name }}</p>
                        <p v-else-if="role?.system" class="mt-1.5 text-xs leading-relaxed text-slate-500 dark:text-brand-gray">
                            Rol del sistema: el nombre no cambia porque el código lo busca así.
                        </p>
                    </section>

                    <!-- Resumen de la selección -->
                    <section :class="[CARD, 'p-5']">
                        <h2 class="flex items-center gap-2 text-[0.68rem] font-bold uppercase tracking-[0.16em] text-slate-400 dark:text-brand-gray/80">
                            <KeyRound class="size-3.5" />
                            Resumen
                        </h2>
                        <p class="mt-3 flex items-baseline gap-1.5">
                            <span class="text-3xl font-bold text-brand tabular-nums dark:text-white">{{ form.permissions.length }}</span>
                            <span class="text-sm text-slate-500 dark:text-brand-gray">de {{ all.length }} permisos</span>
                        </p>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-white/[0.08]">
                            <div
                                class="h-full rounded-full bg-gradient-to-r from-brand-light to-brand transition-[width] duration-300 dark:from-brand-gray dark:to-brand-light"
                                :style="{ width: `${progress}%` }"
                            />
                        </div>
                        <p v-if="form.errors.permissions" class="mt-2 text-xs font-medium text-red-600 dark:text-red-400">{{ form.errors.permissions }}</p>

                        <div
                            v-if="sensitive"
                            role="alert"
                            class="mt-4 flex gap-2.5 rounded-xl border border-amber-300/60 bg-amber-50 px-3.5 py-3 dark:border-amber-400/25 dark:bg-amber-400/10"
                        >
                            <TriangleAlert class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-300" />
                            <p class="text-xs leading-relaxed text-amber-800 dark:text-amber-200/90">
                                <span class="font-bold">Permiso sensible.</span>
                                Con «Administrar roles y permisos» cualquiera de este rol puede concederse todo lo demás.
                            </p>
                        </div>
                    </section>
                </aside>

                <!-- Permisos: solo los existentes, en casillas -->
                <section :class="[CARD, '@container flex flex-col overflow-hidden lg:min-h-0']">
                    <div class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]">
                        <div>
                            <h2 class="text-base font-bold text-brand dark:text-white">Permisos</h2>
                            <p class="text-xs text-slate-500 dark:text-brand-gray">Marca lo que puede hacer quien tenga este rol.</p>
                        </div>
                        <div class="flex gap-1.5">
                            <button
                                type="button"
                                class="h-8 cursor-pointer rounded-lg px-3 text-xs font-semibold text-brand transition-colors hover:bg-brand/[0.07] dark:text-white dark:hover:bg-white/10"
                                @click="selectAll(true)"
                            >
                                Marcar todos
                            </button>
                            <button
                                type="button"
                                class="h-8 cursor-pointer rounded-lg px-3 text-xs font-semibold text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800 dark:text-brand-gray dark:hover:bg-white/10 dark:hover:text-white"
                                @click="selectAll(false)"
                            >
                                Quitar todos
                            </button>
                        </div>
                    </div>

                    <!-- Si algún día hay muchos permisos, desplaza solo esta lista -->
                    <div class="grid content-start gap-3 overflow-y-auto p-4 lg:min-h-0 lg:flex-1 @3xl:grid-cols-2 tall:gap-4 tall:p-5">
                        <fieldset
                            v-for="group in groups"
                            :key="group.key"
                            class="overflow-hidden rounded-xl border border-slate-200/80 dark:border-white/[0.08]"
                        >
                            <legend class="sr-only">{{ group.area }}</legend>

                            <!-- Cabecera del área: su casilla marca o quita todas -->
                            <label
                                class="flex cursor-pointer items-center gap-3 border-b border-slate-100 bg-slate-50/80 px-4 py-2.5 dark:border-white/[0.06] dark:bg-white/[0.03]"
                            >
                                <input
                                    type="checkbox"
                                    :class="CHECK"
                                    :checked="countIn(group) === group.permissions.length"
                                    :indeterminate="countIn(group) > 0 && countIn(group) < group.permissions.length"
                                    @change="toggleGroup(group)"
                                />
                                <span class="flex-1 text-[0.7rem] font-bold uppercase tracking-[0.14em] text-slate-600 dark:text-slate-200">{{ group.area }}</span>
                                <span
                                    class="rounded-md px-1.5 py-0.5 text-[0.65rem] font-bold tabular-nums"
                                    :class="countIn(group) ? 'bg-brand/[0.08] text-brand dark:bg-white/10 dark:text-white' : 'text-slate-400 dark:text-brand-gray/70'"
                                >
                                    {{ countIn(group) }}/{{ group.permissions.length }}
                                </span>
                            </label>

                            <div class="divide-y divide-slate-100 dark:divide-white/[0.05]">
                                <label
                                    v-for="permission in group.permissions"
                                    :key="permission.name"
                                    class="flex cursor-pointer items-center gap-3 px-4 py-2.5 transition-colors duration-150 tall:py-3"
                                    :class="selected.has(permission.name) ? 'bg-brand/[0.03] dark:bg-white/[0.03]' : 'hover:bg-slate-50 dark:hover:bg-white/[0.02]'"
                                >
                                    <input
                                        type="checkbox"
                                        :class="CHECK"
                                        :checked="selected.has(permission.name)"
                                        @change="toggle(permission.name)"
                                    />
                                    <span class="min-w-0 flex-1">
                                        <span
                                            class="block truncate text-sm"
                                            :class="selected.has(permission.name) ? 'font-semibold text-slate-800 dark:text-white' : 'text-slate-600 dark:text-slate-300'"
                                        >
                                            {{ permission.label }}
                                        </span>
                                    </span>
                                    <code class="hidden shrink-0 rounded-md bg-slate-100 px-1.5 py-0.5 font-mono text-[0.65rem] text-slate-500 @lg:block dark:bg-white/[0.06] dark:text-brand-gray">
                                        {{ permission.name }}
                                    </code>
                                </label>
                            </div>
                        </fieldset>
                    </div>
                </section>
            </div>
        </form>
    </AppShell>
</template>
