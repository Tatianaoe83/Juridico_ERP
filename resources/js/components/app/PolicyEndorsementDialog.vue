<script setup>
import { useForm } from '@inertiajs/vue3';
import { CalendarCheck, CircleDollarSign, FilePlus2, FileText, Hash, Loader2, Paperclip, X } from 'lucide-vue-next';
import { nextTick, ref, watch } from 'vue';
import AppModal from '@/components/app/AppModal.vue';
import { shortDate } from '@/lib/units';

/**
 * Registrar un endoso de extensión: la misma póliza, con el mismo número,
 * recorre su fin de vigencia. Lleva el nuevo fin, la prima adicional, el
 * número del endoso si trae y su documento.
 */
const props = defineProps({
    open: { type: Boolean, default: false },
    /** { id, policy, valid_until } */
    policy: { type: Object, default: null },
});

const emit = defineEmits(['update:open']);

/** El día siguiente al fin actual: el nuevo fin tiene que ser después. */
function dayAfter(iso) {
    if (!iso) return undefined;

    const [year, month, day] = iso.split('-').map(Number);

    return new Date(Date.UTC(year, month - 1, day + 1)).toISOString().slice(0, 10);
}

const form = useForm({ endorsement: '', valid_until: '', amount: '', document: null });

const fileInput = ref(null);

// Cada apertura arranca limpia.
watch(
    () => props.open,
    (open) => {
        if (!open) return;

        form.defaults({ endorsement: '', valid_until: '', amount: '', document: null });
        form.reset();
        form.clearErrors();
    },
    { immediate: true },
);

/** El número va en mayúsculas, como todo lo de la póliza; el cursor se queda donde estaba. */
function toUpper(event) {
    const input = event.target;
    const { selectionStart, selectionEnd } = input;

    form.endorsement = input.value.toUpperCase();
    nextTick(() => input.setSelectionRange(selectionStart, selectionEnd));
}

function pickFile(event) {
    form.document = event.target.files[0] ?? null;
    event.target.value = '';
}

function close() {
    emit('update:open', false);
}

function submit() {
    form.transform((data) => ({ ...data, endorsement: data.endorsement || null, amount: data.amount === '' ? null : data.amount }));

    form.post(`/polizas/${props.policy.id}/endosos`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: close,
    });
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

const REQUIRED = 'font-normal text-red-500 dark:text-red-400';
</script>

<template>
    <AppModal
        :open="open"
        size="lg"
        :icon="FilePlus2"
        title="Registrar endoso"
        :description="`Extiende la vigencia de la póliza ${policy?.policy ?? ''} sin cambiarle el número.`"
        @update:open="emit('update:open', $event)"
    >
        <form id="policy-endorsement-form" class="flex flex-col gap-4 px-6 pt-1 pb-6 short:pb-4 sm:px-7" novalidate @submit.prevent="submit">
            <!-- Hasta cuándo va hoy -->
            <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-slate-50/70 px-3 py-2.5 ring-1 ring-inset ring-slate-200/70 dark:bg-white/[0.03] dark:ring-white/[0.06]">
                <p class="text-[0.62rem] font-bold uppercase tracking-[0.14em] text-slate-400 dark:text-brand-gray/80">Fin de vigencia actual</p>
                <p class="text-[0.85rem] font-bold tabular-nums text-slate-800 dark:text-white">{{ shortDate(policy?.valid_until) ?? '—' }}</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="policy-endorsement-until" :class="LABEL">
                        Nuevo fin de vigencia
                        <span :class="REQUIRED">*</span>
                    </label>
                    <div class="relative">
                        <input
                            id="policy-endorsement-until"
                            v-model="form.valid_until"
                            type="date"
                            required
                            :min="dayAfter(policy?.valid_until)"
                            :class="FIELD"
                            :aria-invalid="Boolean(form.errors.valid_until)"
                        />
                        <CalendarCheck :class="ICON" />
                    </div>
                    <p v-if="form.errors.valid_until" :class="ERROR">{{ form.errors.valid_until }}</p>
                </div>

                <div>
                    <label for="policy-endorsement-number" :class="LABEL">Número de endoso</label>
                    <div class="relative">
                        <input
                            id="policy-endorsement-number"
                            :value="form.endorsement"
                            @input="toUpper"
                            type="text"
                            autocomplete="off"
                            placeholder="Si la aseguradora le pone uno"
                            :class="FIELD"
                            :aria-invalid="Boolean(form.errors.endorsement)"
                        />
                        <Hash :class="ICON" />
                    </div>
                    <p v-if="form.errors.endorsement" :class="ERROR">{{ form.errors.endorsement }}</p>
                </div>

                <div>
                    <label for="policy-endorsement-amount" :class="LABEL">Monto (IVA incluido)</label>
                    <div class="relative">
                        <input
                            id="policy-endorsement-amount"
                            v-model="form.amount"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="0.00"
                            :class="[FIELD, 'tabular-nums']"
                            :aria-invalid="Boolean(form.errors.amount)"
                        />
                        <CircleDollarSign :class="ICON" />
                    </div>
                    <p v-if="form.errors.amount" :class="ERROR">{{ form.errors.amount }}</p>
                </div>

                <div>
                    <span :class="LABEL">Documento del endoso</span>
                    <div
                        v-if="form.document"
                        class="flex h-9 items-center gap-2 rounded-lg bg-slate-50 px-2.5 text-[0.75rem] ring-1 ring-inset ring-slate-200 dark:bg-white/[0.04] dark:ring-white/10"
                    >
                        <FileText class="size-3.5 shrink-0 text-slate-400 dark:text-brand-gray" />
                        <span class="min-w-0 flex-1 truncate text-slate-700 dark:text-slate-200" :title="form.document.name">{{ form.document.name }}</span>
                        <button
                            type="button"
                            class="grid size-5 shrink-0 cursor-pointer place-content-center rounded text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10 dark:hover:text-red-300"
                            :aria-label="`Quitar ${form.document.name}`"
                            @click="form.document = null"
                        >
                            <X class="size-3.5" />
                        </button>
                    </div>
                    <button
                        v-else
                        type="button"
                        class="flex h-9 w-full cursor-pointer items-center justify-center gap-2 rounded-lg border border-dashed px-3 text-[0.78rem] font-semibold transition-colors duration-150 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/15"
                        :class="
                            form.errors.document
                                ? 'border-red-400 text-red-600 dark:text-red-400'
                                : 'border-slate-300 bg-slate-50/70 text-slate-600 hover:border-brand/40 hover:bg-brand/[0.03] dark:border-white/15 dark:bg-white/[0.03] dark:text-slate-300 dark:hover:border-white/30'
                        "
                        @click="fileInput?.click()"
                    >
                        <Paperclip class="size-3.5" />
                        Elegir archivo · PDF o imagen
                    </button>
                    <input ref="fileInput" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="hidden" @change="pickFile" />
                    <p v-if="form.errors.document" :class="ERROR">{{ form.errors.document }}</p>
                </div>
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
                form="policy-endorsement-form"
                class="inline-flex h-9 cursor-pointer items-center justify-center gap-2 rounded-xl bg-brand px-4 text-[0.8rem] font-semibold text-white shadow-md shadow-brand/25 transition-all duration-150 hover:bg-brand/90 hover:shadow-lg hover:shadow-brand/30 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/25 active:translate-y-px disabled:pointer-events-none disabled:opacity-60 dark:bg-brand-light dark:shadow-black/30 dark:hover:bg-brand-light/90"
                :disabled="form.processing"
            >
                <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                <FilePlus2 v-else class="size-4" />
                Registrar endoso
            </button>
        </template>
    </AppModal>
</template>
