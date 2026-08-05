<script setup>
import { useForm } from '@inertiajs/vue3';
import { ChevronDown, Eye, EyeOff, Loader2, RefreshCw, UserPlus } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useSwal } from '@/composables/useSwal';

const props = defineProps({
    open: { type: Boolean, default: false },
    /** Nombres de rol existentes, para el desplegable. */
    roles: { type: Array, default: () => [] },
    /** Sin `roles.manage` el alta va con el rol por defecto y no se ofrece elegir. */
    canManageRoles: { type: Boolean, default: false },
});

const emit = defineEmits(['update:open', 'created']);

const { confirm, confirmDelete, blocks } = useSwal();

const DEFAULT_ROLE = 'user';

const form = useForm({
    name: '',
    email: '',
    password: '',
    role: DEFAULT_ROLE,
});

/*
 * Mismas medidas que el componente Input. `bg-none` quita la flecha que dibuja
 * @tailwindcss/forms con background-image, que `appearance-none` no borra y se
 * encimaría con el chevron de Lucide.
 */
const ROLE_SELECT =
    'h-9 w-full appearance-none bg-none rounded-lg border border-input bg-transparent ' +
    'px-2.5 py-1 text-base text-foreground outline-none transition-colors ' +
    'focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 md:text-sm dark:bg-input/30';

/* ---------- Contraseña ---------- */

const revealed = ref(false);

/**
 * La cuenta la crea TI, no su dueño: casi siempre conviene una contraseña
 * generada a que el administrador invente una débil y la repita entre usuarios.
 */
function generate() {
    const alphabet = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%&*';
    const bytes = crypto.getRandomValues(new Uint32Array(16));

    form.password = Array.from(bytes, (byte) => alphabet[byte % alphabet.length]).join('');
    revealed.value = true;
}

/* ---------- Ciclo del diálogo ---------- */

// Cada apertura parte de cero: reabrir con los datos del alta anterior invita a
// crear un duplicado sin querer.
watch(
    () => props.open,
    (open) => {
        if (!open) return;

        form.reset();
        form.clearErrors();
        revealed.value = false;
    },
);

function close() {
    emit('update:open', false);
}

/* ---------- Alta ---------- */

/** El nombre y el correo los teclea una persona; van a HTML sin interpretarse. */
function escape(value) {
    return String(value).replace(
        /[&<>"']/g,
        (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char],
    );
}

async function submit() {
    const role = props.canManageRoles ? form.role : DEFAULT_ROLE;

    const { lead, chip, panel, note, stack } = blocks;

    // Un superadmin salta toda comprobación de permisos: el aviso va en rojo,
    // igual que al promover a alguien desde el listado.
    const critical = role === 'superadmin';

    const ask = critical ? confirmDelete : confirm;

    const ok = await ask({
        title: critical ? '¿Crear una cuenta con acceso total?' : '¿Crear esta cuenta?',
        html: stack(
            lead(`<span class="font-medium">${escape(form.name)}</span>`),
            panel({
                label: 'Datos de la cuenta',
                items: [
                    `Correo: ${chip(escape(form.email))}`,
                    `Rol: ${chip(escape(role))}`,
                    'Contraseña: definida en el formulario',
                ],
            }),
            critical
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
            note('Podrás cambiarle el rol después desde el listado.'),
        ),
        confirmText: critical ? 'Crear con acceso total' : 'Crear usuario',
    });

    if (!ok) return;

    form
        .transform((data) => ({ ...data, roles: [role] }))
        .post('/usuarios', {
            preserveScroll: true,
            onSuccess: () => {
                close();
                emit('created');
            },
        });
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Nuevo usuario</DialogTitle>
                <DialogDescription>
                    La cuenta queda activa al instante. Pásale la contraseña por un medio seguro.
                </DialogDescription>
            </DialogHeader>

            <form id="user-form" class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-1.5">
                    <Label for="name">Nombre</Label>
                    <Input id="name" v-model="form.name" required autofocus maxlength="255" />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="email">Correo</Label>
                    <Input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        maxlength="255"
                        placeholder="nombre@proser.com.mx"
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="password">Contraseña</Label>
                    <div class="flex gap-2">
                        <div class="relative flex-1">
                            <Input
                                id="password"
                                v-model="form.password"
                                :type="revealed ? 'text' : 'password'"
                                required
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

                <div v-if="canManageRoles" class="grid gap-1.5">
                    <Label for="role">Rol</Label>
                    <div class="relative">
                        <select id="role" v-model="form.role" :class="ROLE_SELECT">
                            <option
                                v-for="role in roles"
                                :key="role"
                                :value="role"
                                class="bg-popover text-popover-foreground"
                            >
                                {{ role }}
                            </option>
                        </select>
                        <ChevronDown
                            class="pointer-events-none absolute right-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground"
                        />
                    </div>
                    <InputError :message="form.errors.roles" />
                </div>

                <p v-else class="rounded-lg border bg-muted/30 px-3.5 py-2.5 text-xs text-muted-foreground">
                    La cuenta nacerá con el rol
                    <code class="rounded bg-muted px-1 py-0.5 font-mono">{{ DEFAULT_ROLE }}</code>.
                    Cambiarlo requiere el permiso
                    <code class="rounded bg-muted px-1 py-0.5 font-mono">roles.manage</code>.
                </p>
            </form>

            <DialogFooter>
                <Button type="button" variant="outline" @click="close">Cancelar</Button>
                <Button type="submit" form="user-form" :disabled="form.processing">
                    <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                    <UserPlus v-else class="size-4" />
                    Crear usuario
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
