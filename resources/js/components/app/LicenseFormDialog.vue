<script setup>
import { useForm } from '@inertiajs/vue3';
import { Building2, CalendarDays, FileBadge, FilePen, FilePlus2, Landmark, Loader2, MessageSquareText, Save } from 'lucide-vue-next';
import { computed, watch } from 'vue';
import AppModal from '@/components/app/AppModal.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    /** null = alta · fila de la tabla = edición. */
    license: { type: Object, default: null },
});

const emit = defineEmits(['update:open']);

const editing = computed(() => props.license !== null);

// Sin estatus: el servidor lo calcula con la vigencia.
const form = useForm({
    name: '',
    company: '',
    authority: '',
    valid_until: '',
    comments: '',
});

// Cada apertura parte de cero y, si hay registro, lo carga encima.
watch(
    () => props.open,
    (open) => {
        if (!open) return;

        form.defaults({
            name: props.license?.name ?? '',
            company: props.license?.company ?? '',
            authority: props.license?.authority ?? '',
            valid_until: props.license?.valid_until ?? '',
            comments: props.license?.comments ?? '',
        });
        form.reset();
        form.clearErrors();
    },
);

function close() {
    emit('update:open', false);
}

function submit() {
    // Vacío viaja como null: así la base guarda «sin dato» y no una cadena vacía.
    form.transform((data) => Object.fromEntries(Object.entries(data).map(([key, value]) => [key, value === '' ? null : value])));

    const options = { preserveScroll: true, onSuccess: close };

    editing.value ? form.patch(`/licencias/${props.license.id}`, options) : form.post('/licencias', options);
}

const FIELD =
    'peer h-9 w-full rounded-lg border border-slate-200 bg-slate-50/70 pr-3 pl-9 text-[0.8rem] text-slate-900 outline-none ' +
    'transition-[border-color,background-color,box-shadow] duration-150 placeholder:text-slate-400 ' +
    'hover:border-slate-300 focus:border-brand/50 focus:bg-white focus:ring-4 focus:ring-brand/10 ' +
    'aria-invalid:border-red-400 aria-invalid:focus:ring-red-500/10 ' +
    'dark:border-white/10 dark:bg-white/[0.04] dark:text-white dark:placeholder:text-white/30 dark:hover:border-white/20 ' +
    'dark:focus:border-brand-gray/50 dark:focus:bg-white/[0.06] dark:focus:ring-white/10 dark:[color-scheme:dark]';

const ICON =
    'pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-slate-400 transition-colors ' +
    'peer-focus:text-brand dark:text-white/35 dark:peer-focus:text-white';

const LABEL = 'mb-1.5 block text-[0.75rem] font-semibold text-slate-700 dark:text-slate-200';

const ERROR = 'mt-1 text-[0.7rem] font-medium text-red-600 dark:text-red-400';
</script>

<template>
    <AppModal
        :open="open"
        size="lg"
        :icon="editing ? FilePen : FilePlus2"
        :title="editing ? 'Editar registro' : 'Nuevo registro'"
        :description="
            editing
                ? `Actualiza los datos de ${license?.name}. El estatus se recalcula con la vigencia.`
                : 'Registra una licencia, permiso o trámite. El estatus se calcula con la vigencia.'
        "
        @update:open="emit('update:open', $event)"
    >
        <form id="license-form" class="grid gap-4 px-6 pt-1 pb-6 short:pb-4 sm:grid-cols-2 sm:px-7" novalidate @submit.prevent="submit">
            <div class="sm:col-span-2">
                <label for="license-name" :class="LABEL">Nombre / trámite</label>
                <div class="relative">
                    <input
                        id="license-name"
                        v-model="form.name"
                        type="text"
                        required
                        autofocus
                        autocomplete="off"
                        placeholder="Ej. Licencia de construcción"
                        :class="FIELD"
                        :aria-invalid="Boolean(form.errors.name)"
                    />
                    <FileBadge :class="ICON" />
                </div>
                <p v-if="form.errors.name" :class="ERROR">{{ form.errors.name }}</p>
            </div>

            <div>
                <label for="license-company" :class="LABEL">Empresa</label>
                <div class="relative">
                    <input
                        id="license-company"
                        v-model="form.company"
                        type="text"
                        autocomplete="organization"
                        placeholder="Ej. PROSER Grupo Constructor"
                        :class="FIELD"
                        :aria-invalid="Boolean(form.errors.company)"
                    />
                    <Building2 :class="ICON" />
                </div>
                <p v-if="form.errors.company" :class="ERROR">{{ form.errors.company }}</p>
            </div>

            <div>
                <label for="license-authority" :class="LABEL">Autoridad</label>
                <div class="relative">
                    <input
                        id="license-authority"
                        v-model="form.authority"
                        type="text"
                        autocomplete="off"
                        placeholder="Ej. SEMARNAT"
                        :class="FIELD"
                        :aria-invalid="Boolean(form.errors.authority)"
                    />
                    <Landmark :class="ICON" />
                </div>
                <p v-if="form.errors.authority" :class="ERROR">{{ form.errors.authority }}</p>
            </div>

            <div class="sm:col-span-2">
                <label for="license-valid-until" :class="LABEL">
                    Vigencia
                    <span class="font-normal text-slate-400 dark:text-brand-gray">· vacía si sigue en trámite</span>
                </label>
                <div class="relative sm:max-w-[calc(50%-0.5rem)]">
                    <input
                        id="license-valid-until"
                        v-model="form.valid_until"
                        type="date"
                        :class="FIELD"
                        :aria-invalid="Boolean(form.errors.valid_until)"
                    />
                    <CalendarDays :class="ICON" />
                </div>
                <p v-if="form.errors.valid_until" :class="ERROR">{{ form.errors.valid_until }}</p>
            </div>

            <div class="sm:col-span-2">
                <label for="license-comments" :class="LABEL">Comentarios</label>
                <div class="relative">
                    <textarea
                        id="license-comments"
                        v-model="form.comments"
                        rows="3"
                        placeholder="Notas, requisitos pendientes, contacto en la dependencia…"
                        :class="[FIELD, 'h-auto resize-y py-2 leading-relaxed']"
                        :aria-invalid="Boolean(form.errors.comments)"
                    />
                    <MessageSquareText class="pointer-events-none absolute top-2.5 left-3 size-3.5 text-slate-400 dark:text-white/35" />
                </div>
                <p v-if="form.errors.comments" :class="ERROR">{{ form.errors.comments }}</p>
            </div>
        </form>

        <template #footer>
            <button
                type="button"
                class="h-9 cursor-pointer rounded-xl border border-slate-200 bg-white px-4 text-[0.8rem] font-semibold text-slate-700 transition-colors duration-150 hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-500/10 dark:border-white/10 dark:bg-transparent dark:text-brand-gray dark:hover:bg-white/[0.06] dark:hover:text-white"
                @click="close"
            >
                Cancelar
            </button>
            <button
                type="submit"
                form="license-form"
                class="inline-flex h-9 cursor-pointer items-center justify-center gap-2 rounded-xl bg-brand px-4 text-[0.8rem] font-semibold text-white shadow-md shadow-brand/25 transition-all duration-150 hover:bg-brand/90 hover:shadow-lg hover:shadow-brand/30 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/25 active:translate-y-px disabled:pointer-events-none disabled:opacity-60 dark:bg-brand-light dark:shadow-black/30 dark:hover:bg-brand-light/90"
                :disabled="form.processing"
            >
                <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                <Save v-else class="size-4" />
                {{ editing ? 'Guardar cambios' : 'Guardar' }}
            </button>
        </template>
    </AppModal>
</template>
