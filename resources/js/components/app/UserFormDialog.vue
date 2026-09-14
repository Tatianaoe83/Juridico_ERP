<script setup>
import { useForm } from '@inertiajs/vue3';
import { Check, Eye, EyeOff, Loader2, Lock, Mail, Save, TriangleAlert, UserPen, UserPlus, UserRound } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import AppModal from '@/components/app/AppModal.vue';
import { roleMeta } from '@/lib/users';

const props = defineProps({
    open: { type: Boolean, default: false },
    /** null = alta · usuario de la tabla = edición. */
    user: { type: Object, default: null },
    roles: { type: Array, default: () => [] },
    /** Repartir roles exige `roles.manage`; el servidor lo descarta igual si falta. */
    canManageRoles: { type: Boolean, default: false },
});

const emit = defineEmits(['update:open']);

const form = useForm({ name: '', email: '', password: '', role: '' });

const editing = computed(() => props.user !== null);

// Nadie se cambia el rol a sí mismo: dejaría al sistema sin quien administre.
const showRole = computed(() => props.canManageRoles && !props.user?.is_self);

const showPassword = ref(false);

// Cada apertura parte de cero y, si hay usuario, lo carga encima.
watch(
    () => props.open,
    (open) => {
        if (!open) return;

        form.defaults({
            name: props.user?.name ?? '',
            email: props.user?.email ?? '',
            password: '',
            role: props.user?.roles?.[0] ?? (props.roles.includes('user') ? 'user' : ''),
        });
        form.reset();
        form.clearErrors();
        showPassword.value = false;
    },
    { immediate: true },
);

function close() {
    emit('update:open', false);
}

/**
 * Promover a superadmin concede acceso total: se avisa dentro del propio
 * formulario. Un SweetAlert encima del diálogo no sirve: el diálogo atrapa el
 * foco y bloquea los clics fuera de él, y la alerta queda inerte.
 */
const promoting = computed(
    () => showRole.value && form.role === 'superadmin' && props.user?.roles?.[0] !== 'superadmin',
);

function submit() {
    form.transform((data) => ({
        name: data.name,
        email: data.email,
        // En edición, vacío significa «no cambiar».
        ...(data.password ? { password: data.password } : {}),
        ...(showRole.value && data.role ? { roles: [data.role] } : {}),
    }));

    const options = { preserveScroll: true, onSuccess: close };

    editing.value ? form.patch(`/usuarios/${props.user.id}`, options) : form.post('/usuarios', options);
}

/*
 * <input> nativo y no el componente Input: aquí manda el diseño del modal y
 * así no hay que pisar sus clases una por una. `pl-11` deja sitio al icono.
 */
const FIELD =
    'peer h-11 short:h-10 w-full rounded-xl border border-slate-200 bg-slate-50/60 pr-3.5 pl-11 text-sm text-slate-900 shadow-none ' +
    'outline-none transition-[border-color,background-color,box-shadow] duration-150 placeholder:text-slate-400 ' +
    'hover:border-slate-300 focus:border-brand/60 focus:bg-white focus:ring-4 focus:ring-brand/10 ' +
    'aria-invalid:border-red-400 aria-invalid:focus:ring-red-500/10 ' +
    'dark:border-white/10 dark:bg-white/[0.04] dark:text-white dark:placeholder:text-white/30 dark:hover:border-white/20 ' +
    'dark:focus:border-brand-gray/60 dark:focus:bg-white/[0.06] dark:focus:ring-white/10';

const ICON =
    'pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-slate-400 transition-colors ' +
    'peer-focus:text-brand dark:text-white/35 dark:peer-focus:text-white';

const LABEL = 'mb-1.5 block text-[0.8rem] font-semibold text-slate-700 dark:text-slate-200';

const SECTION = 'mb-3 flex items-center gap-3 text-[0.68rem] font-bold uppercase tracking-[0.16em] text-slate-400 dark:text-brand-gray/80';
</script>

<template>
    <AppModal
        :open="open"
        size="lg"
        :icon="editing ? UserPen : UserPlus"
        :title="editing ? 'Editar usuario' : 'Nuevo usuario'"
        :description="editing ? `Actualiza los datos de ${user?.name}.` : 'Registra una cuenta para que la persona pueda entrar al sistema.'"
        @update:open="emit('update:open', $event)"
    >
        <form id="user-form" class="space-y-7 px-6 pt-1 pb-7 short:space-y-4 short:pb-5 sm:px-7" novalidate @submit.prevent="submit">
            <!-- Datos personales -->
            <section>
                <h3 :class="SECTION">
                    Datos personales
                    <span class="h-px flex-1 bg-slate-100 dark:bg-white/[0.06]" />
                </h3>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="user-name" :class="LABEL">Nombre completo</label>
                        <div class="relative">
                            <input
                                id="user-name"
                                v-model="form.name"
                                type="text"
                                required
                                autofocus
                                maxlength="255"
                                autocomplete="name"
                                placeholder="Ej. María López"
                                :class="FIELD"
                                :aria-invalid="Boolean(form.errors.name)"
                            />
                            <UserRound :class="ICON" />
                        </div>
                        <p v-if="form.errors.name" class="mt-1.5 text-xs font-medium text-red-600 dark:text-red-400">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label for="user-email" :class="LABEL">Correo electrónico</label>
                        <div class="relative">
                            <input
                                id="user-email"
                                v-model="form.email"
                                type="email"
                                required
                                maxlength="255"
                                autocomplete="email"
                                placeholder="nombre@empresa.com"
                                :class="FIELD"
                                :aria-invalid="Boolean(form.errors.email)"
                            />
                            <Mail :class="ICON" />
                        </div>
                        <p v-if="form.errors.email" class="mt-1.5 text-xs font-medium text-red-600 dark:text-red-400">{{ form.errors.email }}</p>
                    </div>
                </div>
            </section>

            <!-- Acceso -->
            <section>
                <h3 :class="SECTION">
                    Acceso
                    <span class="h-px flex-1 bg-slate-100 dark:bg-white/[0.06]" />
                </h3>

                <label for="user-password" :class="LABEL">
                    {{ editing ? 'Nueva contraseña' : 'Contraseña' }}
                    <span v-if="editing" class="font-normal text-slate-400 dark:text-brand-gray">· opcional</span>
                </label>
                <div class="relative">
                    <input
                        id="user-password"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        :required="!editing"
                        autocomplete="new-password"
                        :placeholder="editing ? 'Déjala en blanco para conservar la actual' : 'Mínimo 8 caracteres'"
                        :class="[FIELD, 'pr-12']"
                        :aria-invalid="Boolean(form.errors.password)"
                    />
                    <Lock :class="ICON" />
                    <button
                        type="button"
                        class="absolute top-1/2 right-1.5 grid size-8 -translate-y-1/2 cursor-pointer place-content-center rounded-lg text-slate-400 transition-colors hover:bg-slate-200/60 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-brand/25 dark:hover:bg-white/10 dark:hover:text-white"
                        :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                        @click="showPassword = !showPassword"
                    >
                        <EyeOff v-if="showPassword" class="size-4" />
                        <Eye v-else class="size-4" />
                    </button>
                </div>
                <p v-if="form.errors.password" class="mt-1.5 text-xs font-medium text-red-600 dark:text-red-400">{{ form.errors.password }}</p>
            </section>

            <!-- Rol: tarjetas en vez de desplegable, se ve de un vistazo qué implica cada uno -->
            <section v-if="showRole">
                <h3 :class="SECTION">
                    Rol
                    <span class="h-px flex-1 bg-slate-100 dark:bg-white/[0.06]" />
                </h3>

                <div role="radiogroup" aria-label="Rol" class="grid gap-3 sm:grid-cols-3">
                    <label
                        v-for="role in roles"
                        :key="role"
                        class="group relative flex cursor-pointer flex-col gap-2.5 rounded-xl border p-4 short:flex-row short:items-center short:gap-3 short:p-3 transition-all duration-150 has-[:focus-visible]:ring-4 has-[:focus-visible]:ring-brand/15"
                        :class="
                            form.role === role
                                ? 'border-brand/60 bg-brand/[0.035] shadow-sm shadow-brand/10 dark:border-brand-gray/50 dark:bg-white/[0.06]'
                                : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50 dark:border-white/10 dark:hover:border-white/20 dark:hover:bg-white/[0.03]'
                        "
                    >
                        <input v-model="form.role" type="radio" name="role" :value="role" class="sr-only" />

                        <span
                            class="grid size-9 place-content-center rounded-lg transition-colors"
                            :class="
                                form.role === role
                                    ? 'bg-brand text-white dark:bg-brand-light'
                                    : 'bg-slate-100 text-slate-500 group-hover:text-brand dark:bg-white/[0.06] dark:text-brand-gray dark:group-hover:text-white'
                            "
                        >
                            <component :is="roleMeta(role).icon" class="size-4" />
                        </span>
                        <span>
                            <span class="block text-sm font-bold text-slate-800 dark:text-white">{{ roleMeta(role).label }}</span>
                            <span class="mt-0.5 block text-xs leading-snug text-slate-500 short:hidden dark:text-brand-gray">
                                {{ roleMeta(role).description }}
                            </span>
                        </span>

                        <span
                            class="absolute top-3 right-3 grid size-5 place-content-center rounded-full transition-all duration-150"
                            :class="
                                form.role === role
                                    ? 'scale-100 bg-brand text-white dark:bg-brand-gray dark:text-brand-deep'
                                    : 'scale-75 opacity-0'
                            "
                            aria-hidden="true"
                        >
                            <Check class="size-3" stroke-width="3" />
                        </span>
                    </label>
                </div>
                <p v-if="form.errors.roles || form.errors['roles.0']" class="mt-1.5 text-xs font-medium text-red-600 dark:text-red-400">
                    {{ form.errors.roles || form.errors['roles.0'] }}
                </p>

                <div
                    v-if="promoting"
                    role="alert"
                    class="mt-4 flex gap-3 rounded-xl border border-amber-300/60 bg-amber-50 px-4 py-3.5 dark:border-amber-400/25 dark:bg-amber-400/10"
                >
                    <TriangleAlert class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-300" />
                    <p class="text-xs leading-relaxed text-amber-800 dark:text-amber-200/90">
                        <span class="font-bold">Acceso total.</span>
                        Un superadmin salta toda comprobación de permisos, reparte roles y puede eliminar cualquier
                        cuenta menos la propia.
                    </p>
                </div>
            </section>
        </form>

        <template #footer>
            <button
                type="button"
                class="h-11 short:h-10 cursor-pointer rounded-xl border border-slate-200 bg-white px-5 text-sm font-semibold text-slate-700 transition-colors duration-150 hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-500/10 dark:border-white/10 dark:bg-transparent dark:text-brand-gray dark:hover:bg-white/[0.06] dark:hover:text-white"
                @click="close"
            >
                Cancelar
            </button>
            <button
                type="submit"
                form="user-form"
                class="inline-flex h-11 short:h-10 cursor-pointer items-center justify-center gap-2 rounded-xl bg-brand px-5 text-sm font-semibold text-white shadow-lg shadow-brand/25 transition-all duration-150 hover:bg-brand/90 hover:shadow-xl hover:shadow-brand/30 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/25 active:translate-y-px disabled:pointer-events-none disabled:opacity-60 dark:bg-brand-light dark:shadow-black/30 dark:hover:bg-brand-light/90"
                :disabled="form.processing"
            >
                <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                <Save v-else-if="editing" class="size-4" />
                <UserPlus v-else class="size-4" />
                {{ editing ? 'Guardar cambios' : 'Crear usuario' }}
            </button>
        </template>
    </AppModal>
</template>
