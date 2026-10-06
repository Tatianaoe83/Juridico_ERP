<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { Ban, CalendarClock, CalendarDays, CircleDollarSign, FileCheck, Hash, Landmark, Loader2, MessageSquareText, Save, ShieldCheck, Truck } from 'lucide-vue-next';
import { computed, nextTick } from 'vue';
import FormSelect from '@/components/app/FormSelect.vue';
import { POLICY_COVERAGES } from '@/lib/coverages';
import { SEMESTER_MONTHS, UNIT_TYPES, addMonths, money, periodLabel } from '@/lib/units';

/**
 * El formulario de la póliza, el mismo para el alta y la edición. La vigencia
 * se captura con su inicio: los dos semestres salen de ahí, seis meses cada
 * uno, igual que en el servidor.
 */
const props = defineProps({
    /** null = alta · póliza cargada = edición. */
    policy: { type: Object, default: null },
    /** [{ value, label, type }] */
    units: { type: Array, default: () => [] },
    /** Valores del enum `coverage`. */
    coverages: { type: Array, default: () => [] },
    /** En el alta, la unidad que ya viene elegida. */
    unitId: { type: Number, default: null },
});

/** IVA incluido en los importes. Igual que UnitPolicy::TAX_RATE. */
const TAX_RATE = 0.16;

const editing = computed(() => props.policy !== null);

const form = useForm({
    unit_id: props.policy?.unit_id ?? props.unitId ?? '',
    policy: (props.policy?.policy ?? '').toUpperCase(),
    certificate: (props.policy?.certificate ?? '').toUpperCase(),
    insurer: (props.policy?.insurer ?? '').toUpperCase(),
    coverage: props.policy?.coverage ?? '',
    endorsement: props.policy?.endorsement ?? false,
    valid_from: props.policy?.valid_from ?? '',
    first_payment_amount: props.policy?.first_payment_amount ?? '',
    first_payment_due_on: props.policy?.first_payment_due_on ?? '',
    second_payment_amount: props.policy?.second_payment_amount ?? '',
    second_payment_due_on: props.policy?.second_payment_due_on ?? '',
    // Solo se muestra: no viaja al guardar.
    cancellation_requested_on: props.policy?.cancellation_requested_on ?? '',
    cancelled_on: props.policy?.cancelled_on ?? '',
    comments: (props.policy?.comments ?? '').toUpperCase(),
});

/* ---------- Mayúsculas ---------- */

/**
 * Póliza, certificado, aseguradora y comentarios van siempre en mayúsculas: se convierten
 * mientras escribe. El cursor se deja donde estaba para que corregir a media
 * palabra no lo mande al final.
 */
function toUpper(field, event) {
    const input = event.target;
    const { selectionStart, selectionEnd } = input;

    form[field] = input.value.toUpperCase();
    nextTick(() => input.setSelectionRange(selectionStart, selectionEnd));
}

/* ---------- Opciones ---------- */

const unitOptions = computed(() => props.units.map(({ value, label }) => ({ value, label })));

const selectedUnit = computed(() => props.units.find((unit) => String(unit.value) === String(form.unit_id)) ?? null);

const coverageOptions = computed(() => props.coverages.map((value) => ({ value, label: POLICY_COVERAGES[value] ?? value })));

/* ---------- Vigencia y costo ---------- */

/** Los dos semestres, como los calcula el servidor. */
const semesters = computed(() => {
    if (!form.valid_from) return null;

    const middle = addMonths(form.valid_from, SEMESTER_MONTHS);

    return {
        first: { starts_on: form.valid_from, ends_on: middle },
        second: { starts_on: middle, ends_on: addMonths(middle, SEMESTER_MONTHS) },
    };
});

const total = computed(() => (Number(form.first_payment_amount) || 0) + (Number(form.second_payment_amount) || 0));

const tax = computed(() => Math.round(((total.value * TAX_RATE) / (1 + TAX_RATE)) * 100) / 100);

const INSTALLMENTS = [
    { key: 'first', title: 'Primer pago' },
    { key: 'second', title: 'Segundo pago' },
];

/* ---------- Envío ---------- */

function submit() {
    // Vacío viaja como null para que la base guarde «sin dato».
    // La solicitud de cancelación no se manda: no se captura aquí.
    form.transform(({ cancellation_requested_on, ...data }) =>
        Object.fromEntries(Object.entries(data).map(([key, value]) => [key, value === '' ? null : value])),
    );

    if (editing.value) {
        form.patch(`/polizas/${props.policy.id}`, { preserveScroll: true });
    } else {
        form.post('/polizas', { preserveScroll: true });
    }
}

const FIELD =
    'peer h-9 w-full rounded-lg border border-slate-200 bg-slate-50/70 pr-3 pl-9 text-[0.8rem] text-slate-900 outline-none ' +
    'transition-[border-color,background-color,box-shadow] duration-150 placeholder:text-slate-400 ' +
    'hover:border-slate-300 focus:border-brand/50 focus:bg-white focus:ring-4 focus:ring-brand/10 ' +
    'aria-invalid:border-red-400 aria-invalid:focus:ring-red-500/10 ' +
    'dark:border-white/10 dark:bg-white/[0.04] dark:text-white dark:placeholder:text-white/30 dark:hover:border-white/20 ' +
    'dark:focus:border-brand-gray/50 dark:focus:bg-white/[0.06] dark:focus:ring-white/10 dark:[color-scheme:dark] ' +
    'disabled:cursor-not-allowed disabled:opacity-60';

const ICON =
    'pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-slate-400 transition-colors ' +
    'peer-focus:text-brand dark:text-white/35 dark:peer-focus:text-white';

const LABEL = 'mb-1.5 block text-[0.75rem] font-semibold text-slate-700 dark:text-slate-200';

const ERROR = 'mt-1 text-[0.7rem] font-medium text-red-600 dark:text-red-400';

const REQUIRED = 'font-normal text-red-500 dark:text-red-400';

const CARD =
    'rounded-2xl border border-slate-200/80 bg-white p-4 shadow-[0_1px_3px_rgb(2_29_73/0.04),0_8px_24px_-12px_rgb(2_29_73/0.08)] ' +
    'tall:p-5 dark:border-white/[0.08] dark:bg-brand-deep dark:shadow-none';

const SECTION_TITLE = 'text-[0.62rem] font-bold uppercase tracking-[0.14em] text-slate-400 dark:text-brand-gray/80';
</script>

<template>
    <form id="policy-form" class="flex flex-col gap-3 tall:gap-4" novalidate @submit.prevent="submit">
        <!-- Póliza -->
        <section :class="CARD">
            <p :class="SECTION_TITLE">Póliza</p>

            <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <!-- Unidad: al editar ya no cambia, sus comprobantes van con ella -->
                <div class="sm:col-span-2 lg:col-span-1">
                    <label for="policy-unit" :class="LABEL">
                        Unidad
                        <span v-if="!editing" :class="REQUIRED">*</span>
                    </label>
                    <div
                        v-if="editing"
                        class="flex h-9 items-center gap-2 rounded-lg border border-slate-200 bg-slate-100/70 px-3 text-[0.8rem] text-slate-700 dark:border-white/10 dark:bg-white/[0.03] dark:text-slate-200"
                    >
                        <component :is="UNIT_TYPES[selectedUnit?.type]?.icon ?? Truck" class="size-3.5 shrink-0 text-slate-400 dark:text-brand-gray" />
                        <span class="truncate">{{ selectedUnit?.label ?? '—' }}</span>
                    </div>
                    <FormSelect
                        v-else
                        id="policy-unit"
                        v-model="form.unit_id"
                        :options="unitOptions"
                        :icon="Truck"
                        placeholder="Selecciona una unidad"
                        :invalid="Boolean(form.errors.unit_id)"
                    />
                    <p v-if="!editing && !units.length" class="mt-1 text-[0.7rem] text-slate-400 dark:text-brand-gray/70">Todavía no hay unidades en flotillas.</p>
                    <p v-if="form.errors.unit_id" :class="ERROR">{{ form.errors.unit_id }}</p>
                </div>

                <div>
                    <label for="policy-number" :class="LABEL">
                        Número de póliza
                        <span :class="REQUIRED">*</span>
                    </label>
                    <div class="relative">
                        <input
                            id="policy-number"
                            :value="form.policy"
                            @input="toUpper('policy', $event)"
                            type="text"
                            required
                            autocomplete="off"
                            placeholder="Ej. POL-2026-0148"
                            :class="FIELD"
                            :aria-invalid="Boolean(form.errors.policy)"
                        />
                        <FileCheck :class="ICON" />
                    </div>
                    <p v-if="form.errors.policy" :class="ERROR">{{ form.errors.policy }}</p>
                </div>

                <div>
                    <label for="policy-certificate" :class="LABEL">Certificado</label>
                    <div class="relative">
                        <input
                            id="policy-certificate"
                            :value="form.certificate"
                            @input="toUpper('certificate', $event)"
                            type="text"
                            autocomplete="off"
                            :class="FIELD"
                            :aria-invalid="Boolean(form.errors.certificate)"
                        />
                        <Hash :class="ICON" />
                    </div>
                    <p v-if="form.errors.certificate" :class="ERROR">{{ form.errors.certificate }}</p>
                </div>

                <div>
                    <label for="policy-insurer" :class="LABEL">Aseguradora</label>
                    <div class="relative">
                        <input
                            id="policy-insurer"
                            :value="form.insurer"
                            @input="toUpper('insurer', $event)"
                            type="text"
                            autocomplete="off"
                            placeholder="Ej. Qualitas"
                            :class="FIELD"
                            :aria-invalid="Boolean(form.errors.insurer)"
                        />
                        <Landmark :class="ICON" />
                    </div>
                    <p v-if="form.errors.insurer" :class="ERROR">{{ form.errors.insurer }}</p>
                </div>

                <div>
                    <label for="policy-coverage" :class="LABEL">
                        Cobertura
                        <span :class="REQUIRED">*</span>
                    </label>
                    <FormSelect
                        id="policy-coverage"
                        v-model="form.coverage"
                        :options="coverageOptions"
                        :icon="ShieldCheck"
                        placeholder="Selecciona la cobertura"
                        :invalid="Boolean(form.errors.coverage)"
                    />
                    <p v-if="form.errors.coverage" :class="ERROR">{{ form.errors.coverage }}</p>
                </div>

                <div>
                    <span id="policy-endorsement-label" :class="LABEL">Endoso</span>
                    <!-- Interruptor: la perilla se desliza y el texto cambia con un fundido corto -->
                    <button
                        type="button"
                        role="switch"
                        :aria-checked="form.endorsement"
                        aria-labelledby="policy-endorsement-label"
                        class="group flex h-9 w-full cursor-pointer items-center gap-3 rounded-lg border px-3 text-left transition-[border-color,background-color] duration-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/15"
                        :class="
                            form.endorsement
                                ? 'border-brand/30 bg-brand/[0.05] dark:border-white/25 dark:bg-white/[0.07]'
                                : 'border-slate-200 bg-slate-50/70 hover:border-slate-300 dark:border-white/10 dark:bg-white/[0.04] dark:hover:border-white/20'
                        "
                        @click="form.endorsement = !form.endorsement"
                    >
                        <span
                            class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors duration-200 ease-out motion-reduce:transition-none"
                            :class="form.endorsement ? 'bg-brand dark:bg-brand-light' : 'bg-slate-300 dark:bg-white/20'"
                        >
                            <span
                                class="absolute left-0.5 size-4 rounded-full bg-white shadow-sm transition-transform duration-200 ease-out motion-reduce:transition-none"
                                :class="form.endorsement ? 'translate-x-4' : 'translate-x-0'"
                            />
                        </span>
                        <Transition
                            mode="out-in"
                            enter-active-class="transition-opacity duration-150 ease-out motion-reduce:transition-none"
                            enter-from-class="opacity-0"
                            leave-active-class="transition-opacity duration-100 ease-in motion-reduce:transition-none"
                            leave-to-class="opacity-0"
                        >
                            <span
                                :key="String(form.endorsement)"
                                class="text-[0.8rem] font-semibold"
                                :class="form.endorsement ? 'text-brand dark:text-white' : 'text-slate-500 dark:text-brand-gray'"
                            >
                                {{ form.endorsement ? 'Con endoso USA/Canada' : 'Sin endoso USA/Canada' }}
                            </span>
                        </Transition>
                    </button>
                    <p v-if="form.errors.endorsement" :class="ERROR">{{ form.errors.endorsement }}</p>
                </div>
            </div>
        </section>

        <!-- Vigencia y cuotas -->
        <section :class="CARD">
            <p :class="SECTION_TITLE">Vigencia y cuotas</p>

            <div class="mt-3 grid gap-4 lg:grid-cols-3">
                <div>
                    <label for="policy-valid-from" :class="LABEL">
                        Inicio de vigencia
                        <span :class="REQUIRED">*</span>
                    </label>
                    <div class="relative">
                        <input
                            id="policy-valid-from"
                            v-model="form.valid_from"
                            type="date"
                            required
                            :class="FIELD"
                            :aria-invalid="Boolean(form.errors.valid_from)"
                        />
                        <CalendarDays :class="ICON" />
                    </div>
                    <p v-if="form.errors.valid_from" :class="ERROR">{{ form.errors.valid_from }}</p>
                    <p v-else class="mt-1 text-[0.7rem] text-slate-400 dark:text-brand-gray/70">
                        {{ semesters ? `Vigencia: ${periodLabel({ starts_on: semesters.first.starts_on, ends_on: semesters.second.ends_on })}` : 'Dura un año, en dos semestres.' }}
                    </p>
                </div>

                <!-- El costo se calcula de las dos cuotas, con IVA incluido -->
                <div class="flex flex-col justify-center rounded-xl bg-slate-50/70 px-3.5 py-2.5 ring-1 ring-inset ring-slate-200/70 lg:col-span-2 dark:bg-white/[0.03] dark:ring-white/[0.06]">
                    <p class="text-[0.62rem] font-bold uppercase tracking-[0.14em] text-slate-400 dark:text-brand-gray/80">Costo anual (IVA incluido)</p>
                    <p class="text-xl font-bold tabular-nums text-brand dark:text-white">{{ money(total) }}</p>
                    <p class="text-[0.7rem] tabular-nums text-slate-500 dark:text-brand-gray">IVA {{ Math.round(TAX_RATE * 100) }}%: {{ money(tax) }} · Subtotal: {{ money(total - tax) }}</p>
                </div>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <article
                    v-for="installment in INSTALLMENTS"
                    :key="installment.key"
                    class="rounded-xl border border-slate-200 bg-slate-50/50 p-3.5 dark:border-white/10 dark:bg-white/[0.02]"
                >
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <p class="text-[0.8rem] font-bold text-slate-800 dark:text-white">{{ installment.title }}</p>
                        <p class="text-[0.7rem] tabular-nums text-slate-500 dark:text-brand-gray">{{ semesters ? periodLabel(semesters[installment.key]) : '—' }}</p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label :for="`policy-${installment.key}-amount`" :class="LABEL">Importe</label>
                            <div class="relative">
                                <input
                                    :id="`policy-${installment.key}-amount`"
                                    v-model="form[`${installment.key}_payment_amount`]"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    placeholder="0.00"
                                    :class="[FIELD, 'tabular-nums']"
                                    :aria-invalid="Boolean(form.errors[`${installment.key}_payment_amount`])"
                                />
                                <CircleDollarSign :class="ICON" />
                            </div>
                            <p v-if="form.errors[`${installment.key}_payment_amount`]" :class="ERROR">{{ form.errors[`${installment.key}_payment_amount`] }}</p>
                        </div>

                        <div>
                            <label :for="`policy-${installment.key}-due`" :class="LABEL">Fecha límite de pago</label>
                            <div class="relative">
                                <input
                                    :id="`policy-${installment.key}-due`"
                                    v-model="form[`${installment.key}_payment_due_on`]"
                                    type="date"
                                    :class="FIELD"
                                    :aria-invalid="Boolean(form.errors[`${installment.key}_payment_due_on`])"
                                />
                                <CalendarClock :class="ICON" />
                            </div>
                            <p v-if="form.errors[`${installment.key}_payment_due_on`]" :class="ERROR">{{ form.errors[`${installment.key}_payment_due_on`] }}</p>
                        </div>
                    </div>
                </article>
            </div>
        </section>

        <!-- Cancelación y comentarios -->
        <section :class="CARD">
            <p :class="SECTION_TITLE">Cancelación y comentarios</p>

            <div class="mt-3 grid gap-4 lg:grid-cols-2">
                <div class="grid content-start gap-4 sm:grid-cols-2">
                    <div>
                        <label for="policy-cancel-requested" :class="LABEL">Solicitud de cancelación</label>
                        <!-- No se captura a mano: la llenará el flujo de solicitar cancelación -->
                        <div class="relative">
                            <input
                                id="policy-cancel-requested"
                                :value="form.cancellation_requested_on"
                                type="date"
                                disabled
                                :class="FIELD"
                            />
                            <CalendarClock :class="ICON" />
                        </div>
                    </div>

                    <div>
                        <label for="policy-cancelled" :class="LABEL">Fecha de cancelación</label>
                        <div class="relative">
                            <input
                                id="policy-cancelled"
                                v-model="form.cancelled_on"
                                type="date"
                                :class="FIELD"
                                :aria-invalid="Boolean(form.errors.cancelled_on)"
                            />
                            <Ban :class="ICON" />
                        </div>
                        <p v-if="form.errors.cancelled_on" :class="ERROR">{{ form.errors.cancelled_on }}</p>
                    </div>

                    <p class="text-[0.7rem] text-slate-400 sm:col-span-2 dark:text-brand-gray/70">Solo si se pidió cancelarla. Cancelada ya no se cobran las cuotas pendientes.</p>
                </div>

                <div>
                    <label for="policy-comments" :class="LABEL">Comentarios</label>
                    <div class="relative">
                        <textarea
                            id="policy-comments"
                            :value="form.comments"
                            @input="toUpper('comments', $event)"
                            rows="4"
                            placeholder="Observaciones de la póliza…"
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
                :href="editing ? `/polizas/${policy.id}` : '/polizas'"
                class="inline-flex h-9 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-[0.8rem] font-semibold text-slate-700 transition-colors duration-150 hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-500/10 dark:border-white/10 dark:bg-transparent dark:text-brand-gray dark:hover:bg-white/[0.06] dark:hover:text-white"
            >
                Cancelar
            </Link>
            <button
                type="submit"
                class="inline-flex h-9 cursor-pointer items-center justify-center gap-2 rounded-xl bg-brand px-4 text-[0.8rem] font-semibold text-white shadow-md shadow-brand/25 transition-all duration-150 hover:bg-brand/90 hover:shadow-lg hover:shadow-brand/30 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/25 active:translate-y-px disabled:pointer-events-none disabled:opacity-60 dark:bg-brand-light dark:shadow-black/30 dark:hover:bg-brand-light/90"
                :disabled="form.processing"
            >
                <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                <Save v-else class="size-4" />
                {{ editing ? 'Guardar cambios' : 'Guardar póliza' }}
            </button>
        </div>
    </form>
</template>
