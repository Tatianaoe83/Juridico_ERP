<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import {
    Building2,
    CalendarDays,
    CircleDollarSign,
    FileCheck,
    Hash,
    Landmark,
    Loader2,
    MessageSquareText,
    Paperclip,
    Plus,
    Save,
    Tractor,
    Trash2,
} from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import AppModal from '@/components/app/AppModal.vue';
import FormSelect from '@/components/app/FormSelect.vue';
import MachineDocuments from '@/components/app/MachineDocuments.vue';
import PolicySourceFields from '@/components/app/PolicySourceFields.vue';
import { catalogOptions } from '@/lib/catalogs';
import { MACHINERY_COVERAGES, UNIT_STATUS, fleetListUrl, money } from '@/lib/units';

/**
 * La póliza de maquinaria con sus máquinas, el mismo formulario para el alta y
 * la edición. Un solo pago, en la moneda que diga el contrato, y la vigencia
 * completa; cada máquina lleva sus propios documentos.
 *
 * La del proveedor no lleva pago.
 */
const props = defineProps({
    /** null = alta · póliza cargada con sus máquinas = edición. */
    policy: { type: Object, default: null },
    /** Catálogo: [{ id, name }]. */
    businessUnits: { type: Array, default: () => [] },
    /** Catálogos: [{ id, name }]. */
    insurers: { type: Array, default: () => [] },
    beneficiaries: { type: Array, default: () => [] },
    /** Valores del enum `status` de la máquina. */
    statuses: { type: Array, default: () => [] },
    /** Coberturas de la póliza de maquinaria: EC y RC. */
    coverages: { type: Array, default: () => [] },
    /** { cobertura: moneda sugerida }, igual que UnitPolicy::MACHINERY_CURRENCIES. */
    currencies: { type: Object, default: () => ({}) },
    /** Monedas que se pueden elegir: UnitPolicy::CURRENCIES. */
    currencyCodes: { type: Array, default: () => ['MXN', 'USD'] },
    /** En el alta: con qué origen abre la póliza. */
    initialSource: { type: String, default: 'corporate' },
});

const editing = computed(() => props.policy !== null);

/** Para el v-for: las nuevas aún no tienen id. */
let nextKey = 0;

function machine(values = {}) {
    return {
        key: ++nextKey,
        id: values.id ?? null,
        machine_type: (values.machine_type ?? '').toUpperCase(),
        brand: (values.brand ?? '').toUpperCase(),
        model: (values.model ?? '').toUpperCase(),
        economic_number: values.economic_number ?? '',
        status: values.status ?? 'active',
        // Los guardados solo se muestran; no viajan al servidor.
        saved: values.evidences ?? [],
        evidences: [],
        remove_evidences: [],
    };
}

const form = useForm({
    policy: (props.policy?.policy ?? '').toUpperCase(),
    certificate: (props.policy?.certificate ?? '').toUpperCase(),
    insurer_id: props.policy?.insurer_id ?? '',
    coverage: props.policy?.coverage ?? '',
    currency: props.policy?.currency ?? '',
    business_unit_id: props.policy?.business_unit_id ?? '',
    source: props.policy?.source ?? props.initialSource,
    supplier_id: props.policy?.supplier_id ?? '',
    valid_from: props.policy?.valid_from ?? '',
    valid_until: props.policy?.valid_until ?? '',
    amount: props.policy?.amount ?? '',
    comments: props.policy?.comments ?? '',
    machines: props.policy?.machines?.length ? props.policy.machines.map(machine) : [machine()],
});

/* ---------- Póliza ---------- */

const coverageOptions = computed(() => props.coverages.map((value) => ({ value, label: MACHINERY_COVERAGES[value]?.label ?? value })));

const CURRENCY_LABELS = { MXN: 'MXN · PESOS', USD: 'USD · DÓLARES' };

const currencyOptions = computed(() => props.currencyCodes.map((value) => ({ value, label: CURRENCY_LABELS[value] ?? value })));

/** Al elegir cobertura se sugiere su moneda habitual; depende del contrato, así que se puede cambiar. */
watch(
    () => form.coverage,
    (coverage) => {
        if (props.currencies[coverage]) form.currency = props.currencies[coverage];
    },
);

const currency = computed(() => form.currency || null);

/** IVA incluido en el monto. Igual que UnitPolicy::TAX_RATE. */
const TAX_RATE = 0.16;

/** El IVA que ya viene dentro del monto: se desglosa, no se suma. */
const tax = computed(() => Math.round((((Number(form.amount) || 0) * TAX_RATE) / (1 + TAX_RATE)) * 100) / 100);

const fromSupplier = computed(() => form.source === 'supplier');

/* ---------- Máquinas ---------- */

const statusOptions = computed(() =>
    props.statuses.map((value) => ({ value, label: (UNIT_STATUS[value]?.label ?? value).toUpperCase(), dot: UNIT_STATUS[value]?.dot })),
);

const insurerOptions = computed(() => catalogOptions(props.insurers));

const businessUnitOptions = computed(() => props.businessUnits.map(({ id, name }) => ({ value: id, label: name.toUpperCase() })));

function addMachine() {
    form.machines.push(machine());
    nextTick(() => document.getElementById(`machine-${form.machines.at(-1).key}-type`)?.focus());
}

function removeMachine(index) {
    documentsIndex.value = null;
    form.machines.splice(index, 1);
}

/** Los errores llegan por índice: machines.0.brand, machines.1.evidences.0… */
function machineError(index, field) {
    return form.errors[`machines.${index}.${field}`];
}

function documentErrors(index) {
    return Object.entries(form.errors)
        .filter(([key]) => key.startsWith(`machines.${index}.evidences`) || key.startsWith(`machines.${index}.remove_evidences`))
        .map(([, message]) => message);
}

/* ---------- Documentos ---------- */

/** La máquina cuyo modal de documentos está abierto; null = cerrado. */
const documentsIndex = ref(null);

const documentsMachine = computed(() => (documentsIndex.value === null ? null : (form.machines[documentsIndex.value] ?? null)));

function openDocuments(index) {
    documentsIndex.value = index;
}

/** Los que quedarán al guardar: guardados que se conservan más los nuevos. */
function documentCount(item) {
    return item.saved.filter((file) => !item.remove_evidences.includes(file.id)).length + item.evidences.length;
}

/* ---------- Mayúsculas ---------- */

/** Igual que en la unidad: se convierten mientras escribe, sin mover el cursor. */
function toUpper(target, field, event) {
    const input = event.target;
    const { selectionStart, selectionEnd } = input;

    target[field] = input.value.toUpperCase();
    nextTick(() => input.setSelectionRange(selectionStart, selectionEnd));
}

/* ---------- Envío ---------- */

const empty = (value) => (value === '' ? null : value);

function submit() {
    form.transform((data) => ({
        ...Object.fromEntries(Object.entries(data).filter(([key]) => key !== 'machines').map(([key, value]) => [key, empty(value)])),
        machines: data.machines.map(({ key, saved, ...values }) => ({
            ...Object.fromEntries(Object.entries(values).map(([field, value]) => [field, empty(value)])),
            evidences: values.evidences,
            remove_evidences: values.remove_evidences,
        })),
        // Con archivos la petición va como POST; el método real viaja aquí.
        ...(editing.value ? { _method: 'patch' } : {}),
    })).post(editing.value ? `/flotillas/maquinaria/${props.policy.id}` : '/flotillas/maquinaria', {
        forceFormData: true,
        // Con índices: con corchetes vacíos cada campo de una máquina caería
        // en una máquina distinta.
        queryStringArrayFormat: 'indices',
    });
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

/* ---------- Filas de máquinas ---------- */

/** Las columnas de la tabla de máquinas; en angostas cada fila es una tarjeta de 2 columnas. */
const ROW_GRID =
    'lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,0.8fr)_minmax(0,1fr)_4.5rem_2rem] lg:gap-x-2';

const CELL =
    'h-8 w-full rounded-lg border border-slate-200 bg-slate-50/70 px-2.5 text-[0.78rem] text-slate-900 outline-none ' +
    'transition-[border-color,background-color,box-shadow] duration-150 placeholder:text-slate-400 ' +
    'hover:border-slate-300 focus:border-brand/50 focus:bg-white focus:ring-4 focus:ring-brand/10 ' +
    'aria-invalid:border-red-400 aria-invalid:focus:ring-red-500/10 ' +
    'dark:border-white/10 dark:bg-white/[0.04] dark:text-white dark:placeholder:text-white/30 dark:hover:border-white/20 ' +
    'dark:focus:border-brand-gray/50 dark:focus:bg-white/[0.06] dark:focus:ring-white/10';

/** En la tabla la etiqueta la da el encabezado; en angostas se ve sobre el campo. */
const CELL_LABEL = 'mb-1 block text-[0.7rem] font-semibold text-slate-600 lg:sr-only dark:text-slate-300';

const CELL_ERROR = 'mt-0.5 text-[0.65rem] leading-tight font-medium text-red-600 dark:text-red-400';
</script>

<template>
    <form class="flex flex-col gap-3 tall:gap-4" novalidate @submit.prevent="submit">
        <!-- Póliza -->
        <section :class="CARD">
            <p :class="SECTION_TITLE">Póliza de maquinaria</p>

            <!-- Primero de quién es: la unidad de negocio de toda la póliza y el beneficiario preferente -->
            <PolicySourceFields :form="form" :beneficiaries="beneficiaries" prefix="machinery" class="mt-3">
                <div>
                    <label for="machinery-business" :class="LABEL">Unidad de negocio <span :class="REQUIRED">*</span></label>
                    <FormSelect
                        id="machinery-business"
                        v-model="form.business_unit_id"
                        :options="businessUnitOptions"
                        :icon="Building2"
                        placeholder="Selecciona una unidad"
                        :invalid="Boolean(form.errors.business_unit_id)"
                    />
                    <p v-if="form.errors.business_unit_id" :class="ERROR">{{ form.errors.business_unit_id }}</p>
                    <p v-else class="mt-1 text-[0.7rem] text-slate-400 dark:text-brand-gray/70">La de todas las máquinas de la póliza.</p>
                </div>
            </PolicySourceFields>

            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label for="machinery-insurer" :class="LABEL">Aseguradora</label>
                    <FormSelect
                        id="machinery-insurer"
                        v-model="form.insurer_id"
                        :options="insurerOptions"
                        :icon="Landmark"
                        placeholder="Selecciona la aseguradora"
                        :invalid="Boolean(form.errors.insurer_id)"
                    />
                    <p v-if="!insurerOptions.length" class="mt-1 text-[0.7rem] text-slate-400 dark:text-brand-gray/70">Todavía no hay aseguradoras en el catálogo.</p>
                    <p v-if="form.errors.insurer_id" :class="ERROR">{{ form.errors.insurer_id }}</p>
                </div>

                <div>
                    <label for="machinery-policy" :class="LABEL">Número de póliza <span :class="REQUIRED">*</span></label>
                    <div class="relative">
                        <input
                            id="machinery-policy"
                            :value="form.policy"
                            @input="toUpper(form, 'policy', $event)"
                            type="text"
                            required
                            autocomplete="off"
                            :class="FIELD"
                            :aria-invalid="Boolean(form.errors.policy)"
                        />
                        <FileCheck :class="ICON" />
                    </div>
                    <p v-if="form.errors.policy" :class="ERROR">{{ form.errors.policy }}</p>
                </div>

                <div>
                    <label for="machinery-certificate" :class="LABEL">Certificado</label>
                    <div class="relative">
                        <input
                            id="machinery-certificate"
                            :value="form.certificate"
                            @input="toUpper(form, 'certificate', $event)"
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
                    <label for="machinery-coverage" :class="LABEL">Cobertura <span :class="REQUIRED">*</span></label>
                    <FormSelect
                        id="machinery-coverage"
                        v-model="form.coverage"
                        :options="coverageOptions"
                        placeholder="Selecciona una cobertura"
                        :invalid="Boolean(form.errors.coverage)"
                    />
                    <p v-if="form.errors.coverage" :class="ERROR">{{ form.errors.coverage }}</p>
                </div>
            </div>
        </section>

        <!-- Vigencia y pago: un solo pago; la del proveedor, solo la vigencia -->
        <section :class="CARD">
            <p :class="SECTION_TITLE">{{ fromSupplier ? 'Vigencia' : 'Vigencia y pago' }}</p>

            <div class="mt-3 grid gap-4 sm:grid-cols-2" :class="fromSupplier ? 'lg:grid-cols-3' : 'lg:grid-cols-4'">
                <div>
                    <label for="machinery-from" :class="LABEL">Inicio de vigencia <span :class="REQUIRED">*</span></label>
                    <div class="relative">
                        <input id="machinery-from" v-model="form.valid_from" type="date" required :class="FIELD" :aria-invalid="Boolean(form.errors.valid_from)" />
                        <CalendarDays :class="ICON" />
                    </div>
                    <p v-if="form.errors.valid_from" :class="ERROR">{{ form.errors.valid_from }}</p>
                </div>

                <div>
                    <label for="machinery-until" :class="LABEL">Fin de vigencia <span :class="REQUIRED">*</span></label>
                    <div class="relative">
                        <input
                            id="machinery-until"
                            v-model="form.valid_until"
                            type="date"
                            required
                            :min="form.valid_from || undefined"
                            :class="FIELD"
                            :aria-invalid="Boolean(form.errors.valid_until)"
                        />
                        <CalendarDays :class="ICON" />
                    </div>
                    <p v-if="form.errors.valid_until" :class="ERROR">{{ form.errors.valid_until }}</p>
                </div>

                <p v-if="fromSupplier" class="self-center text-[0.72rem] text-slate-500 dark:text-brand-gray">
                    La paga el proveedor o va dentro del contrato: aquí no se registran pagos.
                </p>

                <div v-if="!fromSupplier">
                    <label for="machinery-currency" :class="LABEL">Moneda <span :class="REQUIRED">*</span></label>
                    <FormSelect
                        id="machinery-currency"
                        v-model="form.currency"
                        :options="currencyOptions"
                        :icon="CircleDollarSign"
                        placeholder="Selecciona una moneda"
                        :invalid="Boolean(form.errors.currency)"
                    />
                    <p v-if="form.errors.currency" :class="ERROR">{{ form.errors.currency }}</p>
                </div>

                <div v-if="!fromSupplier">
                    <label for="machinery-amount" :class="LABEL">Monto a pagar</label>
                    <div class="relative">
                        <input
                            id="machinery-amount"
                            v-model="form.amount"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="0.00"
                            :class="[FIELD, 'pr-14 tabular-nums']"
                            :aria-invalid="Boolean(form.errors.amount)"
                        />
                        <CircleDollarSign :class="ICON" />
                        <span
                            class="pointer-events-none absolute top-1/2 right-2 -translate-y-1/2 rounded-md bg-slate-100 px-1.5 py-0.5 text-[0.65rem] font-bold text-slate-500 dark:bg-white/10 dark:text-brand-gray"
                        >
                            {{ currency ?? '—' }}
                        </span>
                    </div>
                    <p v-if="form.errors.amount" :class="ERROR">{{ form.errors.amount }}</p>
                    <p v-else-if="currency && form.amount" class="mt-1 text-[0.7rem] tabular-nums text-slate-400 dark:text-brand-gray/70">
                        {{ money(form.amount, currency) }} · IVA {{ Math.round(TAX_RATE * 100) }}%: {{ money(tax, currency) }} · Subtotal:
                        {{ money(form.amount - tax, currency) }}
                    </p>
                </div>
            </div>
        </section>

        <!-- Máquinas: una fila por máquina; sus documentos, en un modal -->
        <section :class="CARD">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p :class="SECTION_TITLE">Máquinas ({{ form.machines.length }})</p>
                <button
                    type="button"
                    class="inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-[0.75rem] font-semibold text-slate-700 transition-colors duration-150 hover:bg-slate-50 hover:text-brand focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/10 disabled:pointer-events-none disabled:opacity-50 dark:border-white/10 dark:bg-transparent dark:text-brand-gray dark:hover:bg-white/[0.06] dark:hover:text-white"
                    @click="addMachine"
                >
                    <Plus class="size-3.5" />
                    Agregar máquina
                </button>
            </div>
            <p v-if="form.errors.machines" :class="ERROR">{{ form.errors.machines }}</p>

            <div class="mt-3 overflow-hidden rounded-xl border border-slate-200 dark:border-white/10">
                <!-- Encabezados: solo en pantallas anchas; en angostas cada campo lleva su etiqueta -->
                <div
                    :class="ROW_GRID"
                    class="hidden border-b border-slate-200 bg-slate-50 px-3 py-2 text-[0.62rem] font-bold tracking-[0.1em] text-slate-500 uppercase lg:grid dark:border-white/10 dark:bg-white/[0.03] dark:text-brand-gray"
                >
                    <span>Tipo <span :class="REQUIRED">*</span></span>
                    <span>Marca <span :class="REQUIRED">*</span></span>
                    <span>Modelo <span :class="REQUIRED">*</span></span>
                    <span># Económico</span>
                    <span>Estado <span :class="REQUIRED">*</span></span>
                    <span class="text-center">Docs</span>
                    <span class="sr-only">Quitar</span>
                </div>

                <div
                    v-for="(item, index) in form.machines"
                    :key="item.key"
                    :class="ROW_GRID"
                    class="grid grid-cols-2 gap-x-2 gap-y-2 border-b border-slate-100 px-3 py-2.5 last:border-b-0 lg:items-start lg:py-2 dark:border-white/[0.06]"
                >
                    <!-- Solo en angostas: de qué máquina es la tarjeta -->
                    <p class="col-span-2 flex items-center gap-2 text-[0.78rem] font-bold text-slate-800 lg:hidden dark:text-white">
                        <Tractor class="size-4 text-slate-400 dark:text-brand-gray" />
                        Máquina {{ index + 1 }}
                        <span v-if="!item.id" class="rounded bg-brand/[0.07] px-1.5 py-px text-[0.6rem] font-bold text-brand uppercase dark:bg-white/10 dark:text-white">Nueva</span>
                    </p>

                    <div class="col-span-2 lg:col-span-1">
                        <label :for="`machine-${item.key}-type`" :class="CELL_LABEL">Tipo <span :class="REQUIRED">*</span></label>
                        <input
                            :id="`machine-${item.key}-type`"
                            :value="item.machine_type"
                            @input="toUpper(item, 'machine_type', $event)"
                            type="text"
                            autocomplete="off"
                            placeholder="Ej. RETROEXCAVADORA"
                            :class="CELL"
                            :aria-invalid="Boolean(machineError(index, 'machine_type'))"
                        />
                        <p v-if="machineError(index, 'machine_type')" :class="CELL_ERROR">{{ machineError(index, 'machine_type') }}</p>
                    </div>

                    <div>
                        <label :for="`machine-${item.key}-brand`" :class="CELL_LABEL">Marca <span :class="REQUIRED">*</span></label>
                        <input
                            :id="`machine-${item.key}-brand`"
                            :value="item.brand"
                            @input="toUpper(item, 'brand', $event)"
                            type="text"
                            autocomplete="off"
                            placeholder="Ej. CATERPILLAR"
                            :class="CELL"
                            :aria-invalid="Boolean(machineError(index, 'brand'))"
                        />
                        <p v-if="machineError(index, 'brand')" :class="CELL_ERROR">{{ machineError(index, 'brand') }}</p>
                    </div>

                    <div>
                        <label :for="`machine-${item.key}-model`" :class="CELL_LABEL">Modelo <span :class="REQUIRED">*</span></label>
                        <input
                            :id="`machine-${item.key}-model`"
                            :value="item.model"
                            @input="toUpper(item, 'model', $event)"
                            type="text"
                            autocomplete="off"
                            :class="CELL"
                            :aria-invalid="Boolean(machineError(index, 'model'))"
                        />
                        <p v-if="machineError(index, 'model')" :class="CELL_ERROR">{{ machineError(index, 'model') }}</p>
                    </div>

                    <div>
                        <label :for="`machine-${item.key}-economic`" :class="CELL_LABEL"># Económico</label>
                        <input
                            :id="`machine-${item.key}-economic`"
                            v-model="item.economic_number"
                            type="text"
                            autocomplete="off"
                            :class="[CELL, 'tabular-nums']"
                            :aria-invalid="Boolean(machineError(index, 'economic_number'))"
                        />
                        <p v-if="machineError(index, 'economic_number')" :class="CELL_ERROR">{{ machineError(index, 'economic_number') }}</p>
                    </div>

                    <div class="col-span-2 sm:col-span-1">
                        <label :for="`machine-${item.key}-status`" :class="CELL_LABEL">Estado <span :class="REQUIRED">*</span></label>
                        <FormSelect
                            :id="`machine-${item.key}-status`"
                            v-model="item.status"
                            :options="statusOptions"
                            compact
                            :invalid="Boolean(machineError(index, 'status'))"
                        />
                        <p v-if="machineError(index, 'status')" :class="CELL_ERROR">{{ machineError(index, 'status') }}</p>
                    </div>

                    <!-- Documentos: el botón dice cuántos lleva y abre el modal -->
                    <div class="col-span-2 flex items-center gap-2 lg:col-span-1 lg:justify-center">
                        <button
                            type="button"
                            class="relative inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-lg border px-2.5 text-[0.75rem] font-semibold tabular-nums transition-colors duration-150 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/10"
                            :class="
                                documentErrors(index).length
                                    ? 'border-red-400 text-red-600 hover:bg-red-50 dark:border-red-400/60 dark:text-red-300 dark:hover:bg-red-500/10'
                                    : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-brand dark:border-white/10 dark:bg-transparent dark:text-brand-gray dark:hover:bg-white/[0.06] dark:hover:text-white'
                            "
                            :aria-label="`Documentos de la máquina ${index + 1}`"
                            title="Documentos"
                            @click="openDocuments(index)"
                        >
                            <Paperclip class="size-3.5" />
                            {{ documentCount(item) || '—' }}
                            <!-- Punto de marca: hay cambios por guardar -->
                            <span
                                v-if="item.evidences.length || item.remove_evidences.length"
                                class="absolute -top-1 -right-1 size-2 rounded-full bg-brand ring-2 ring-white dark:bg-brand-gray dark:ring-brand-deep"
                                title="Cambios por guardar"
                            />
                        </button>
                        <span class="text-[0.7rem] text-slate-400 lg:hidden dark:text-brand-gray/70">Documentos</span>
                    </div>

                    <div class="col-span-2 flex justify-end lg:col-span-1 lg:block">
                        <button
                            type="button"
                            class="grid size-8 cursor-pointer place-content-center rounded-lg text-slate-400 transition-colors hover:bg-red-50 hover:text-red-600 disabled:pointer-events-none disabled:opacity-30 dark:text-brand-gray dark:hover:bg-red-500/10 dark:hover:text-red-300"
                            :disabled="form.machines.length === 1"
                            :aria-label="`Quitar la máquina ${index + 1}`"
                            :title="item.id ? 'Al guardar se elimina la máquina con sus documentos' : 'Quitar'"
                            @click="removeMachine(index)"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Documentos de la máquina elegida -->
        <AppModal
            :open="documentsMachine !== null"
            :title="`Documentos · Máquina ${documentsIndex + 1}`"
            :description="documentsMachine ? [documentsMachine.machine_type, documentsMachine.brand, documentsMachine.model].filter(Boolean).join(' · ') || 'Sin datos todavía' : ''"
            :icon="Paperclip"
            size="lg"
            @update:open="(open) => !open && (documentsIndex = null)"
        >
            <div v-if="documentsMachine" class="px-6 pb-6 sm:px-7">
                <MachineDocuments
                    v-model:files="documentsMachine.evidences"
                    v-model:removed="documentsMachine.remove_evidences"
                    :saved="documentsMachine.saved"
                    :unit-id="documentsMachine.id"
                    :errors="documentErrors(documentsIndex)"
                />
                <p class="mt-3 text-[0.7rem] text-slate-400 dark:text-brand-gray/70">Los cambios se aplican al guardar la póliza.</p>
            </div>

            <template #footer>
                <button
                    type="button"
                    class="inline-flex h-9 cursor-pointer items-center justify-center rounded-xl bg-brand px-4 text-[0.8rem] font-semibold text-white shadow-md shadow-brand/25 transition-all duration-150 hover:bg-brand/90 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/25 dark:bg-brand-light dark:shadow-black/30 dark:hover:bg-brand-light/90"
                    @click="documentsIndex = null"
                >
                    Listo
                </button>
            </template>
        </AppModal>

        <!-- Comentarios -->
        <section :class="CARD">
            <label for="machinery-comments" :class="[SECTION_TITLE, 'mb-3 block']">Comentarios</label>
            <div class="relative">
                <textarea
                    id="machinery-comments"
                    v-model="form.comments"
                    rows="2"
                    placeholder="Observaciones de la póliza y sus máquinas…"
                    :class="[FIELD, 'h-auto resize-y py-2 leading-relaxed']"
                    :aria-invalid="Boolean(form.errors.comments)"
                />
                <MessageSquareText class="pointer-events-none absolute top-2.5 left-3 size-3.5 text-slate-400 dark:text-white/35" />
            </div>
            <p v-if="form.errors.comments" :class="ERROR">{{ form.errors.comments }}</p>
        </section>

        <!-- Acciones -->
        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <Link
                :href="fleetListUrl(policy?.source ?? initialSource, true)"
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
