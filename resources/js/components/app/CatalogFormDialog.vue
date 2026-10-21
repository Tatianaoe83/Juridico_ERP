<script setup>
import { useForm } from '@inertiajs/vue3';
import { Loader2, Pencil, Plus, Save } from 'lucide-vue-next';
import { computed, nextTick, watch } from 'vue';
import AppModal from '@/components/app/AppModal.vue';
import { CATALOGS } from '@/lib/catalogs';

const props = defineProps({
    open: { type: Boolean, default: false },
    /** Slug del catálogo: 'aseguradoras', 'beneficiarios'… */
    catalog: { type: String, required: true },
    /** null = alta · registro de la tabla = edición. */
    item: { type: Object, default: null },
});

const emit = defineEmits(['update:open']);

const meta = computed(() => CATALOGS[props.catalog]);

const form = useForm({ name: '' });

const editing = computed(() => props.item !== null);

// Cada apertura parte de cero y, si hay registro, lo carga encima.
watch(
    () => props.open,
    (open) => {
        if (!open) return;

        form.defaults({ name: props.item?.name ?? '' });
        form.reset();
        form.clearErrors();
    },
    { immediate: true },
);

/** Igual que en los formularios: en mayúsculas mientras escribe, sin mover el cursor. */
function input(event) {
    const field = event.target;

    if (!meta.value.uppercase) {
        form.name = field.value;
        return;
    }

    const { selectionStart, selectionEnd } = field;

    form.name = field.value.toUpperCase();
    nextTick(() => field.setSelectionRange(selectionStart, selectionEnd));
}

function close() {
    emit('update:open', false);
}

function submit() {
    const options = { preserveScroll: true, onSuccess: close };
    const url = `/catalogos/${props.catalog}`;

    editing.value ? form.patch(`${url}/${props.item.id}`, options) : form.post(url, options);
}

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
</script>

<template>
    <AppModal
        :open="open"
        size="sm"
        :icon="editing ? Pencil : Plus"
        :title="editing ? `Editar ${meta.singular}` : meta.create"
        :description="editing ? `Cambia el nombre de ${item?.name}.` : 'Queda disponible en los formularios en cuanto la guardes.'"
        @update:open="emit('update:open', $event)"
    >
        <form id="catalog-form" class="px-6 pt-1 pb-7 short:pb-5 sm:px-7" novalidate @submit.prevent="submit">
            <label for="catalog-name" class="mb-1.5 block text-[0.8rem] font-semibold text-slate-700 dark:text-slate-200">Nombre</label>
            <div class="relative">
                <input
                    id="catalog-name"
                    :value="form.name"
                    @input="input"
                    type="text"
                    required
                    autofocus
                    autocomplete="off"
                    :placeholder="meta.placeholder"
                    :class="FIELD"
                    :aria-invalid="Boolean(form.errors.name)"
                />
                <component :is="meta.icon" :class="ICON" />
            </div>
            <p v-if="form.errors.name" class="mt-1.5 text-xs font-medium text-red-600 dark:text-red-400">{{ form.errors.name }}</p>
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
                form="catalog-form"
                class="inline-flex h-11 short:h-10 cursor-pointer items-center justify-center gap-2 rounded-xl bg-brand px-5 text-sm font-semibold text-white shadow-lg shadow-brand/25 transition-all duration-150 hover:bg-brand/90 hover:shadow-xl hover:shadow-brand/30 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/25 active:translate-y-px disabled:pointer-events-none disabled:opacity-60 dark:bg-brand-light dark:shadow-black/30 dark:hover:bg-brand-light/90"
                :disabled="form.processing"
            >
                <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                <Save v-else-if="editing" class="size-4" />
                <Plus v-else class="size-4" />
                {{ editing ? 'Guardar cambios' : 'Guardar' }}
            </button>
        </template>
    </AppModal>
</template>
