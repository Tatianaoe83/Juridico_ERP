<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ChevronDown, Eye, EyeOff, Loader2, RefreshCw, Save } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppShell from '@/Layouts/AppShell.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useSwal } from '@/composables/useSwal';

const props = defineProps({
    person: { type: Object, required: true },
    roles: { type: Array, default: () => [] },
    canManageRoles: { type: Boolean, default: false },
});

const breadcrumbs = [
    { label: 'Inicio', href: '/calendario' },
    { label: 'Usuarios', href: '/usuarios' },
    { label: props.person.name, href: `/usuarios/${props.person.id}` },
    { label: 'Editar' },
];

const { confirm, confirmDelete, blocks } = useSwal();

const originalRole = props.person.roles[0] ?? '';

const form = useForm({
    name: props.person.name,
    email: props.person.email,
    // Vacío = no se toca. La contraseña actual está hasheada y no se puede mostrar.
    password: '',
    role: originalRole,
});

/** Cambiarse el rol a uno mismo dejaría al sistema sin quien administre. */
const roleLocked = computed(() => !props.canManageRoles || props.person.is_self);

const ROLE_SELECT =
    'h-9 w-full appearance-none bg-none rounded-lg border border-input bg-transparent ' +
    'px-2.5 py-1 text-base text-foreground outline-none transition-colors ' +
    'focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 ' +
    'disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30';

/* ---------- Contraseña ---------- */

const revealed = ref(false);

function generate() {
    const alphabet = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%&*';
    const bytes = crypto.getRandomValues(new Uint32Array(16));

    form.password = Array.from(bytes, (byte) => alphabet[byte % alphabet.length]).join('');
    revealed.value = true;
}

/* ---------- Guardado ---------- */

function escape(value) {
    return String(value).replace(
        /[&<>"']/g,
        (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char],
    );
}

/** Solo lo que de verdad cambió: un resumen con todo repetido no se lee. */
const changes = computed(() => {
    const { chip, arrow } = blocks;
    const list = [];

    if (form.name !== props.person.name) {
        list.push(`Nombre: ${chip(escape(props.person.name))}${arrow()}${chip(escape(form.name))}`);
    }

    if (form.email !== props.person.email) {
        list.push(`Correo: ${chip(escape(props.person.email))}${arrow()}${chip(escape(form.email))}`);
    }

    if (form.password) {
        list.push('Contraseña: se reemplaza por una nueva');
    }

    if (!roleLocked.value && form.role !== originalRole) {
        list.push(`Rol: ${chip(escape(originalRole || 'sin rol'))}${arrow()}${chip(escape(form.role))}`);
    }

    return list;
});

async function submit() {
    if (!changes.value.length) return;

    const { lead, panel, note, stack } = blocks;

    // Promover a superadmin concede acceso total: merece el aviso rojo.
    const promoting = !roleLocked.value && form.role === 'superadmin' && originalRole !== 'superadmin';

    const ask = promoting ? confirmDelete : confirm;

    const ok = await ask({
        title: promoting ? '¿Conceder acceso total?' : '¿Guardar estos cambios?',
        html: stack(
            lead(`<span class="font-medium">${escape(props.person.name)}</span>`),
            panel({ label: 'Qué cambia', items: changes.value }),
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
                : null,
            form.email !== props.person.email
                ? note('Cambiar el correo no reenvía la verificación ni afecta su cuenta de Microsoft.')
                : null,
        ),
        confirmText: promoting ? 'Conceder' : 'Guardar cambios',
    });

    if (!ok) return;

    form
        .transform(({ role, password, ...rest }) => ({
            ...rest,
            // El backend ignora la contraseña vacía, pero mandarla enturbia el log.
            ...(password ? { password } : {}),
            ...(roleLocked.value ? {} : { roles: [role] }),
        }))
        .patch(`/usuarios/${props.person.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Editar · ${person.name}`" />

    <AppShell :breadcrumbs="breadcrumbs">
        <!-- Columna centrada: el formulario es angosto y pegado a la izquierda
             dejaba media pantalla vacía a su derecha -->
        <div class="mx-auto w-full max-w-2xl">
            <div class="mb-5 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Editar usuario</h1>
                    <p class="text-sm text-muted-foreground">{{ person.email }}</p>
                </div>

                <Button variant="outline" class="gap-1.5" as-child>
                    <Link :href="`/usuarios/${person.id}`">
                        <ArrowLeft class="size-4" />
                        Volver a la ficha
                    </Link>
                </Button>
            </div>

            <form class="rounded-xl border bg-card p-4" @submit.prevent="submit">
                <div class="grid gap-4">
                    <div class="grid gap-1.5">
                        <Label for="name">Nombre</Label>
                        <Input id="name" v-model="form.name" required maxlength="255" />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="email">Correo</Label>
                        <Input id="email" v-model="form.email" type="email" required maxlength="255" />
                        <InputError :message="form.errors.email" />
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="password">
                            Contraseña <span class="text-muted-foreground">(dejar vacío para no cambiarla)</span>
                        </Label>
                        <div class="flex gap-2">
                            <div class="relative flex-1">
                                <Input
                                    id="password"
                                    v-model="form.password"
                                    :type="revealed ? 'text' : 'password'"
                                    autocomplete="new-password"
                                    class="pr-9 font-mono"
                                />
                                <button
                                    type="button"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground transition-colors hover:text-foreground"
                                    :aria-label="revealed ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                                    @click="revealed = !revealed"
                                >
                                    <EyeOff v-if="revealed" class="size-4" />
                                    <Eye v-else class="size-4" />
                                </button>
                            </div>
                            <Button type="button" variant="outline" class="shrink-0 gap-1.5" @click="generate">
                                <RefreshCw class="size-4" />
                                Generar
                            </Button>
                        </div>
                        <InputError :message="form.errors.password" />
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="role">Rol</Label>
                        <div class="relative">
                            <select id="role" v-model="form.role" :disabled="roleLocked" :class="ROLE_SELECT">
                                <option v-for="role in roles" :key="role" :value="role" class="bg-popover text-popover-foreground">
                                    {{ role }}
                                </option>
                            </select>
                            <ChevronDown
                                class="pointer-events-none absolute right-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground"
                            />
                        </div>

                        <p v-if="person.is_self" class="text-xs text-muted-foreground">
                            No puedes cambiar tu propio rol: degradarte dejaría al sistema sin quien
                            administre.
                        </p>
                        <p v-else-if="!canManageRoles" class="text-xs text-muted-foreground">
                            Cambiar el rol requiere el permiso
                            <code class="rounded bg-muted px-1 py-0.5 font-mono">roles.manage</code>.
                        </p>

                        <InputError :message="form.errors.roles" />
                    </div>
                </div>

                <div class="mt-5 flex items-center justify-end gap-2 border-t pt-4">
                    <span v-if="!changes.length" class="mr-auto text-xs text-muted-foreground">
                        Sin cambios por guardar
                    </span>

                    <Button variant="outline" as-child>
                        <Link :href="`/usuarios/${person.id}`">Cancelar</Link>
                    </Button>
                    <Button type="submit" :disabled="form.processing || !changes.length">
                        <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                        <Save v-else class="size-4" />
                        Guardar cambios
                    </Button>
                </div>
            </form>
        </div>
    </AppShell>
</template>
