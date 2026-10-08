<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import {
    Ban,
    Building,
    Building2,
    CalendarCheck,
    CalendarClock,
    CalendarDays,
    CalendarPlus,
    CircleDollarSign,
    FileText,
    Link2,
    Loader2,
    MessageSquareText,
    Package,
    Save,
    ShieldCheck,
    UserRound,
} from 'lucide-vue-next';
import { computed, nextTick } from 'vue';
import FormSelect from '@/components/app/FormSelect.vue';
import { BOND_CATEGORIES } from '@/lib/coverages';

/** El formulario de la fianza, el mismo para el alta y la edición. */
const props = defineProps({
    /** null = alta · fianza cargada = edición. */
    bond: { type: Object, default: null },
    /** Catálogo: [{ id, name }]. */
    businessUnits: { type: Array, default: () => [] },
    /** Valores del enum `category`. */
    categories: { type: Array, default: () => [] },
});

const editing = computed(() => props.bond !== null);

const FIELDS = [
    'business_unit_id',
    'bond',
    'beneficiary',
    'bonding_company',
    'amount',
    'requested_on',
    'issued_on',
    'valid_from',
    'valid_until',
    'source_document',
    'category',
    'related',
    'cancellation_requested_on',
    'cancelled_on',
    'comments',
];

/** Los de texto libre van siempre en mayúsculas. Igual que SaveBondRequest::UPPERCASE. */
const UPPERCASE = ['bond', 'bonding_company', 'beneficiary', 'related', 'source_document', 'comments'];

const form = useForm(
    Object.fromEntries(
        FIELDS.map((field) => {
            const value = props.bond?.[field] ?? '';

            return [field, UPPERCASE.includes(field) ? String(value).toUpperCase() : value];
        }),
    ),
);

/**
 * Se convierten a mayúsculas mientras escribe. El cursor se deja donde estaba
 * para que corregir a media palabra no lo mande al final.
 */
function toUpper(field, event) {
    const input = event.target;
    const { selectionStart, selectionEnd } = input;

    form[field] = input.value.toUpperCase();
    nextTick(() => input.setSelectionRange(selectionStart, selectionEnd));
}

const businessUnitOptions = computed(() => props.businessUnits.map(({ id, name }) => ({ value: id, label: name })));

const categoryOptions = computed(() => props.categories.map((value) => ({ value, label: BOND_CATEGORIES[value] ?? value })));

/** Los de texto, en el orden en que se leen en el detalle. */
const TEXTS = [
    { field: 'bond', label: 'Número de fianza', icon: ShieldCheck, required: true, placeholder: 'Ej. FZA-9988-B' },
    { field: 'bonding_company', label: 'Afianzadora', icon: Building, placeholder: 'Ej. Fianzas Monterrey' },
    { field: 'beneficiary', label: 'Beneficiario', icon: UserRound },
    { field: 'related', label: 'Relativo', icon: Link2, placeholder: 'Contrato o asunto al que se refiere' },
    { field: 'source_document', label: 'Docto. fuente', icon: FileText, placeholder: 'Ej. Contrato 045/2026' },
];

/** Las fechas en el orden en que pasan: se pide, se emite y corre la vigencia. */
const DATES = [
    { field: 'requested_on', label: 'Solicitud de emisión', icon: CalendarPlus },
    { field: 'issued_on', label: 'Fecha de emisión', icon: CalendarCheck },
    { field: 'valid_from', label: 'Inicio de vigencia', icon: CalendarDays },
    { field: 'valid_until', label: 'Fin de vigencia', icon: CalendarDays },
];

const CANCELLATION = [
    { field: 'cancellation_requested_on', label: 'Solicitud de cancelación', icon: CalendarClock },
    { field: 'cancelled_on', label: 'Fecha de cancelación', icon: Ban },
];

function submit() {
    // Vacío viaja como null para que la base guarde «sin dato».
    form.transform((data) => Object.fromEntries(Object.entries(data).map(([key, value]) => [key, value === '' ? null : value])));

    if (editing.value) {
        form.patch(`/polizas/fianzas/${props.bond.id}`, { preserveScroll: true });
    } else {
        form.post('/polizas/fianzas', { preserveScroll: true });
    }
}

const FIELD =
    'peer h-8 w-full rounded-lg border border-slate-200 bg-slate-50/70 pr-3 pl-9 text-[0.8rem] text-slate-900 outline-none ' +
    'transition-[border-color,background-color,box-shadow] duration-150 placeholder:text-slate-400 ' +
    'hover:border-slate-300 focus:border-brand/50 focus:bg-white focus:ring-4 focus:ring-brand/10 ' +
    'aria-invalid:border-red-400 aria-invalid:focus:ring-red-500/10 ' +
    'dark:border-white/10 dark:bg-white/[0.04] dark:text-white dark:placeholder:text-white/30 dark:hover:border-white/20 ' +
    'dark:focus:border-brand-gray/50 dark:focus:bg-white/[0.06] dark:focus:ring-white/10 dark:[color-scheme:dark]';

const ICON =
    'pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-slate-400 transition-colors ' +
    'peer-focus:text-brand dark:text-white/35 dark:peer-focus:text-white';

const LABEL = 'mb-1 block text-[0.72rem] font-semibold text-slate-700 dark:text-slate-200';

const ERROR = 'mt-1 text-[0.7rem] font-medium text-red-600 dark:text-red-400';

const REQUIRED = 'font-normal text-red-500 dark:text-red-400';

const CARD =
    'rounded-2xl border border-slate-200/80 bg-white px-4 py-3 shadow-[0_1px_3px_rgb(2_29_73/0.04),0_8px_24px_-12px_rgb(2_29_73/0.08)] ' +
    'tall:py-3.5 dark:border-white/[0.08] dark:bg-brand-deep dark:shadow-none';

const SECTION_TITLE = 'text-[0.62rem] font-bold uppercase tracking-[0.14em] text-slate-400 dark:text-brand-gray/80';
</script>

<template>
    <form id="bond-form" class="flex flex-col gap-2.5" novalidate @submit.prevent="submit">
        <!-- Fianza -->
        <section :class="CARD">
            <p :class="SECTION_TITLE">Fianza</p>

            <div class="mt-2 grid gap-x-4 gap-y-2.5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <div v-for="input in TEXTS" :key="input.field">
                    <label :for="`bond-${input.field}`" :class="LABEL">
                        {{ input.label }}
                        <span v-if="input.required" :class="REQUIRED">*</span>
                    </label>
                    <div class="relative">
                        <input
                            :id="`bond-${input.field}`"
                            :value="form[input.field]"
                            @input="toUpper(input.field, $event)"
                            type="text"
                            :required="input.required"
                            autocomplete="off"
                            :placeholder="input.placeholder"
                            :class="FIELD"
                            :aria-invalid="Boolean(form.errors[input.field])"
                        />
                        <component :is="input.icon" :class="ICON" />
                    </div>
                    <p v-if="form.errors[input.field]" :class="ERROR">{{ form.errors[input.field] }}</p>
                </div>

                <div>
                    <label for="bond-amount" :class="LABEL">
                        Monto
                        <span :class="REQUIRED">*</span>
                    </label>
                    <div class="relative">
                        <input
                            id="bond-amount"
                            v-model="form.amount"
                            type="number"
                            required
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
                    <label for="bond-business" :class="LABEL">
                        Unidad de negocio
                        <span :class="REQUIRED">*</span>
                    </label>
                    <FormSelect
                        compact
                        id="bond-business"
                        v-model="form.business_unit_id"
                        :options="businessUnitOptions"
                        :icon="Building2"
                        placeholder="Selecciona una unidad"
                        :invalid="Boolean(form.errors.business_unit_id)"
                    />
                    <p v-if="!businessUnits.length" class="mt-1 text-[0.7rem] text-slate-400 dark:text-brand-gray/70">
                        Todavía no hay unidades de negocio en el catálogo.
                    </p>
                    <p v-if="form.errors.business_unit_id" :class="ERROR">{{ form.errors.business_unit_id }}</p>
                </div>

                <div>
                    <label for="bond-category" :class="LABEL">Categoría</label>
                    <FormSelect
                        compact
                        id="bond-category"
                        v-model="form.category"
                        :options="categoryOptions"
                        :icon="Package"
                        placeholder="Selecciona la categoría"
                        :invalid="Boolean(form.errors.category)"
                    />
                    <p v-if="form.errors.category" :class="ERROR">{{ form.errors.category }}</p>
                </div>
            </div>
        </section>

        <!-- Emisión y vigencia -->
        <section :class="CARD">
            <p :class="SECTION_TITLE">Emisión y vigencia</p>

            <div class="mt-2 grid gap-x-4 gap-y-2.5 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="input in DATES" :key="input.field">
                    <label :for="`bond-${input.field}`" :class="LABEL">{{ input.label }}</label>
                    <div class="relative">
                        <input
                            :id="`bond-${input.field}`"
                            v-model="form[input.field]"
                            type="date"
                            :class="FIELD"
                            :aria-invalid="Boolean(form.errors[input.field])"
                        />
                        <component :is="input.icon" :class="ICON" />
                    </div>
                    <p v-if="form.errors[input.field]" :class="ERROR">{{ form.errors[input.field] }}</p>
                </div>
            </div>
        </section>

        <!-- Cancelación y comentarios: una fianza nueva no llega cancelada, la cancelación solo al editar -->
        <section :class="CARD">
            <p :class="SECTION_TITLE">{{ editing ? 'Cancelación y comentarios' : 'Comentarios' }}</p>

            <div :class="['mt-2 grid gap-x-4 gap-y-2.5', editing && 'lg:grid-cols-2']">
                <div v-if="editing" class="grid content-start gap-x-4 gap-y-2.5 sm:grid-cols-2">
                    <div v-for="input in CANCELLATION" :key="input.field">
                        <label :for="`bond-${input.field}`" :class="LABEL">{{ input.label }}</label>
                        <div class="relative">
                            <input
                                :id="`bond-${input.field}`"
                                v-model="form[input.field]"
                                type="date"
                                :class="FIELD"
                                :aria-invalid="Boolean(form.errors[input.field])"
                            />
                            <component :is="input.icon" :class="ICON" />
                        </div>
                        <p v-if="form.errors[input.field]" :class="ERROR">{{ form.errors[input.field] }}</p>
                    </div>

                    <p class="text-[0.7rem] text-slate-400 sm:col-span-2 dark:text-brand-gray/70">Solo si se pidió cancelarla.</p>
                </div>

                <div>
                    <label for="bond-comments" :class="LABEL">Comentarios</label>
                    <div class="relative">
                        <textarea
                            id="bond-comments"
                            :value="form.comments"
                            @input="toUpper('comments', $event)"
                            rows="2"
                            placeholder="Observaciones de la fianza…"
                            :class="[FIELD, 'h-auto resize-y py-2 leading-relaxed']"
                            :aria-invalid="Boolean(form.errors.comments)"
                        />
                        <MessageSquareText class="pointer-events-none absolute top-2.5 left-3 size-3.5 text-slate-400 dark:text-white/35" />
                    </div>
                    <p v-if="form.errors.comments" :class="ERROR">{{ form.errors.comments }}</p>
                </div>
            </div>
        </section>

        <!-- Acciones: al final del formulario, no flotando -->
        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <Link
                :href="editing ? `/polizas/fianzas/${bond.id}` : '/polizas'"
                class="inline-flex h-8 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-[0.8rem] font-semibold text-slate-700 transition-colors duration-150 hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-500/10 dark:border-white/10 dark:bg-transparent dark:text-brand-gray dark:hover:bg-white/[0.06] dark:hover:text-white"
            >
                Cancelar
            </Link>
            <button
                type="submit"
                class="inline-flex h-8 cursor-pointer items-center justify-center gap-2 rounded-xl bg-brand px-4 text-[0.8rem] font-semibold text-white shadow-md shadow-brand/25 transition-all duration-150 hover:bg-brand/90 hover:shadow-lg hover:shadow-brand/30 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/25 active:translate-y-px disabled:pointer-events-none disabled:opacity-60 dark:bg-brand-light dark:shadow-black/30 dark:hover:bg-brand-light/90"
                :disabled="form.processing"
            >
                <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                <Save v-else class="size-4" />
                {{ editing ? 'Guardar cambios' : 'Guardar fianza' }}
            </button>
        </div>
    </form>
</template>
