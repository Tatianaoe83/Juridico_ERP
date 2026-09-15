<script setup>
import { useForm } from '@inertiajs/vue3';
import { KeyRound, Loader2, Pencil, Plus, Save } from 'lucide-vue-next';
import { computed, watch } from 'vue';
import AppModal from '@/components/app/AppModal.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    /** null = alta · permiso de la tabla = edición. */
    permission: { type: Object, default: null },
});

const emit = defineEmits(['update:open']);

const form = useForm({ name: '' });

const editing = computed(() => props.permission !== null);

// Cada apertura parte de cero y, si hay permiso, lo carga encima.
watch(
    () => props.open,
    (open) => {
        if (!open) return;

        form.defaults({ name: props.permission?.name ?? '' });
        form.reset();
        form.clearErrors();
    },
    { immediate: true },
);

function close() {
    emit('update:open', false);
}

function submit() {
    const options = { preserveScroll: true, onSuccess: close };

    editing.value ? form.patch(`/permisos/${props.permission.id}`, options) : form.post('/permisos', options);
}

const FIELD =
    'peer h-11 short:h-10 w-full rounded-xl border border-slate-200 bg-slate-50/60 pr-3.5 pl-11 font-mono text-sm text-slate-900 shadow-none ' +
    'outline-none transition-[border-color,background-color,box-shadow] duration-150 placeholder:font-sans placeholder:text-slate-400 ' +
    'hover:border-slate-300 focus:border-brand/60 focus:bg-white focus:ring-4 focus:ring-brand/10 ' +
    'aria-invalid:border-red-400 aria-invalid:focus:ring-red-500/10 ' +
    'dark:border-white/10 dark:bg-white/[0.04] dark:text-white dark:placeholder:text-white/30 dark:hover:border-white/20 ' +
    'dark:focus:border-brand-gray/60 dark:focus:bg-white/[0.06] dark:focus:ring-white/10';

const ICON =
    'pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-slate-400 transition-colors ' +
    'peer-focus:text-brand dark:text-white/35 dark:peer-focus:text-white';
</script>

<template>
    <AppModal
        :open="open"
        size="sm"
        :icon="editing ? Pencil : Plus"
        :title="editing ? 'Editar permiso' : 'Nuevo permiso'"
        :description="editing ? `Cambia el nombre de ${permission?.name}.` : 'Agrega un permiso para poder asignarlo después a un rol.'"
        @update:open="emit('update:open', $event)"
    >
        <form id="permission-form" class="px-6 pt-1 pb-7 short:pb-5 sm:px-7" novalidate @submit.prevent="submit">
            <label for="permission-name" class="mb-1.5 block text-[0.8rem] font-semibold text-slate-700 dark:text-slate-200">Nombre</label>
            <div class="relative">
                <input
                    id="permission-name"
                    v-model="form.name"
                    type="text"
                    required
                    autofocus
                    autocomplete="off"
                    placeholder="ej. documentos.ver"
                    :class="FIELD"
                    :aria-invalid="Boolean(form.errors.name)"
                />
                <KeyRound :class="ICON" />
            </div>
            <p v-if="form.errors.name" class="mt-1.5 text-xs font-medium text-red-600 dark:text-red-400">{{ form.errors.name }}</p>
            <p v-else class="mt-1.5 text-xs leading-relaxed text-slate-500 dark:text-brand-gray">
                Lo que va antes del punto es el área donde se agrupa.
            </p>
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
                form="permission-form"
                class="inline-flex h-11 short:h-10 cursor-pointer items-center justify-center gap-2 rounded-xl bg-brand px-5 text-sm font-semibold text-white shadow-lg shadow-brand/25 transition-all duration-150 hover:bg-brand/90 hover:shadow-xl hover:shadow-brand/30 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/25 active:translate-y-px disabled:pointer-events-none disabled:opacity-60 dark:bg-brand-light dark:shadow-black/30 dark:hover:bg-brand-light/90"
                :disabled="form.processing"
            >
                <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                <Save v-else-if="editing" class="size-4" />
                <Plus v-else class="size-4" />
                {{ editing ? 'Guardar cambios' : 'Crear permiso' }}
            </button>
        </template>
    </AppModal>
</template>
