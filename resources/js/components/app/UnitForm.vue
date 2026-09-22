<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import {
    BadgeCheck,
    Building2,
    CalendarDays,
    CircleDollarSign,
    FileText,
    Hash,
    Loader2,
    CalendarClock,
    MessageSquareText,
    Paperclip,
    RotateCcw,
    Save,
    ScanLine,
    Truck,
    User,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import FormSelect from '@/components/app/FormSelect.vue';
import { UNIT_STATUS, money, paymentCountdown, periodLabel } from '@/lib/units';

/**
 * El formulario de la unidad, el mismo para el alta y la edición: solo cambia
 * de dónde salen los valores iniciales y a qué ruta se envía.
 */
const props = defineProps({
    /** null = alta · unidad cargada = edición. */
    unit: { type: Object, default: null },
    /** Catálogo: [{ id, name }]. Puede venir vacío si nadie lo ha llenado. */
    businessUnits: { type: Array, default: () => [] },
    /** Valores del enum `status`, tal como los acepta el servidor. */
    statuses: { type: Array, default: () => [] },
});

const editing = computed(() => props.unit !== null);

const form = useForm({
    policy: props.unit?.policy ?? '',
    certificate: props.unit?.certificate ?? '',
    business_unit_id: props.unit?.business_unit_id ?? '',
    brand: props.unit?.brand ?? '',
    model: props.unit?.model ?? '',
    serial_number: props.unit?.serial_number ?? '',
    plate: props.unit?.plate ?? '',
    economic_number: props.unit?.economic_number ?? '',
    responsible: props.unit?.responsible ?? '',
    first_payment_starts_on: props.unit?.first_payment_starts_on ?? '',
    first_payment_ends_on: props.unit?.first_payment_ends_on ?? '',
    first_payment_amount: props.unit?.first_payment_amount ?? '',
    second_payment_starts_on: props.unit?.second_payment_starts_on ?? '',
    second_payment_ends_on: props.unit?.second_payment_ends_on ?? '',
    second_payment_amount: props.unit?.second_payment_amount ?? '',
    usa_canada_endorsement: props.unit?.usa_canada_endorsement ?? false,
    status: props.unit?.status ?? 'active',
    comments: props.unit?.comments ?? '',
    evidences: [],
    // Ids de las evidencias que ya estaban y se quitan al guardar.
    remove_evidences: [],
});

/* ---------- Costo anual: el mismo cálculo que hace el servidor ---------- */



const TAX_RATE = 0.16;

// Los importes se capturan como se pagan, con IVA incluido: el impuesto se
// desglosa del costo anual en vez de sumarse encima.
const annualCost = computed(() => Number(form.first_payment_amount || 0) + Number(form.second_payment_amount || 0));
const tax = computed(() => (annualCost.value * TAX_RATE) / (1 + TAX_RATE));
const subtotal = computed(() => annualCost.value - tax.value);

/* ---------- Cómo va cada semestre ---------- */

// Salen de lo que hay en el formulario, no del registro guardado: al mover una
// fecha el periodo y el aviso cambian en el momento.
const firstPeriod = computed(() =>
    periodLabel({ starts_on: form.first_payment_starts_on, ends_on: form.first_payment_ends_on }),
);

const secondPeriod = computed(() =>
    periodLabel({ starts_on: form.second_payment_starts_on, ends_on: form.second_payment_ends_on }),
);

// El semestre vence cuando cierra, así que el aviso sale de la fecha «Hasta».
const firstCountdown = computed(() => paymentCountdown(form.first_payment_ends_on));
const secondCountdown = computed(() => paymentCountdown(form.second_payment_ends_on));

/* ---------- Evidencias ---------- */

const fileInput = ref(null);

/** Las que ya están guardadas, menos las que se marcaron para quitar. */
const savedEvidences = computed(() => (props.unit?.evidences ?? []).filter((e) => !form.remove_evidences.includes(e.id)));

function addFiles(event) {
    // Se suman a las que ya eligió: abrir el selector otra vez no borra lo anterior.
    form.evidences = [...form.evidences, ...Array.from(event.target.files)];

    // El input se limpia para poder volver a elegir el mismo archivo.
    event.target.value = '';
}

function removeFile(index) {
    form.evidences = form.evidences.filter((_, i) => i !== index);
}

/** Marcar y desmarcar: nada se borra en el servidor hasta guardar. */
function removeSaved(id) {
    form.remove_evidences = [...form.remove_evidences, id];
}

function restoreSaved(id) {
    form.remove_evidences = form.remove_evidences.filter((value) => value !== id);
}

/** «1.4 MB», «812 KB»: el peso importa porque el límite es de 10 MB por archivo. */
function fileSize(bytes) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

/* ---------- Envío ---------- */

// Con su punto de color: el estado se reconoce antes de leerlo.
const statusOptions = computed(() =>
    props.statuses.map((value) => ({ value, label: UNIT_STATUS[value]?.label ?? value, dot: UNIT_STATUS[value]?.dot })),
);

const businessUnitOptions = computed(() => props.businessUnits.map(({ id, name }) => ({ value: id, label: name })));

function submit() {
    // Vacío viaja como null para que la base guarde «sin dato» y no una cadena
    // vacía; el booleano va como 1/0, que es lo que entiende multipart.
    form.transform((data) => ({
        ...Object.fromEntries(Object.entries(data).map(([key, value]) => [key, value === '' ? null : value])),
        usa_canada_endorsement: data.usa_canada_endorsement ? 1 : 0,
        evidences: data.evidences,
        remove_evidences: data.remove_evidences,
        // Con archivos la petición va como POST; el método real viaja aquí.
        ...(editing.value ? { _method: 'patch' } : {}),
    })).post(editing.value ? `/flotillas/${props.unit.id}` : '/flotillas', { forceFormData: true });
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

const CARD =
    'rounded-2xl border border-slate-200/80 bg-white p-4 shadow-[0_1px_3px_rgb(2_29_73/0.04),0_8px_24px_-12px_rgb(2_29_73/0.08)] ' +
    'tall:p-5 dark:border-white/[0.08] dark:bg-brand-deep dark:shadow-none';

const SECTION_TITLE = 'text-[0.62rem] font-bold uppercase tracking-[0.14em] text-slate-400 dark:text-brand-gray/80';
</script>

<template>
    <form id="unit-form" class="flex flex-col gap-3 tall:gap-4" novalidate @submit.prevent="submit">
        <!-- Identificación -->
        <section :class="CARD">
            <p :class="SECTION_TITLE">Identificación</p>

            <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="unit-policy" :class="LABEL">
                        Póliza
                        <span class="font-normal text-red-500 dark:text-red-400">*</span>
                    </label>
                    <div class="relative">
                        <input
                            id="unit-policy"
                            v-model="form.policy"
                            type="text"
                            required
                            autofocus
                            autocomplete="off"
                            placeholder="Ej. POL-2026-0148"
                            :class="FIELD"
                            :aria-invalid="Boolean(form.errors.policy)"
                        />
                        <BadgeCheck :class="ICON" />
                    </div>
                    <p v-if="form.errors.policy" :class="ERROR">{{ form.errors.policy }}</p>
                </div>

                <div>
                    <label for="unit-certificate" :class="LABEL">Certificado</label>
                    <div class="relative">
                        <input
                            id="unit-certificate"
                            v-model="form.certificate"
                            type="text"
                            autocomplete="off"
                            placeholder="Ej. CERT-99812"
                            :class="FIELD"
                            :aria-invalid="Boolean(form.errors.certificate)"
                        />
                        <FileText :class="ICON" />
                    </div>
                    <p v-if="form.errors.certificate" :class="ERROR">{{ form.errors.certificate }}</p>
                </div>

                <div>
                    <label for="unit-business" :class="LABEL">Unidad de negocio</label>
                    <FormSelect
                        id="unit-business"
                        v-model="form.business_unit_id"
                        :options="businessUnitOptions"
                        :icon="Building2"
                        placeholder="Sin asignar"
                        :invalid="Boolean(form.errors.business_unit_id)"
                    />
                    <p v-if="!businessUnits.length" class="mt-1 text-[0.7rem] text-slate-400 dark:text-brand-gray/70">
                        Todavía no hay unidades de negocio en el catálogo.
                    </p>
                    <p v-if="form.errors.business_unit_id" :class="ERROR">{{ form.errors.business_unit_id }}</p>
                </div>
            </div>
        </section>

        <!-- Vehículo -->
        <section :class="CARD">
            <p :class="SECTION_TITLE">Vehículo</p>

            <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="unit-brand" :class="LABEL">
                        Marca
                        <span class="font-normal text-red-500 dark:text-red-400">*</span>
                    </label>
                    <div class="relative">
                        <input
                            id="unit-brand"
                            v-model="form.brand"
                            type="text"
                            required
                            autocomplete="off"
                            placeholder="Ej. Nissan"
                            :class="FIELD"
                            :aria-invalid="Boolean(form.errors.brand)"
                        />
                        <Truck :class="ICON" />
                    </div>
                    <p v-if="form.errors.brand" :class="ERROR">{{ form.errors.brand }}</p>
                </div>

                <div>
                    <label for="unit-model" :class="LABEL">
                        Modelo
                        <span class="font-normal text-red-500 dark:text-red-400">*</span>
                    </label>
                    <div class="relative">
                        <input
                            id="unit-model"
                            v-model="form.model"
                            type="text"
                            required
                            autocomplete="off"
                            placeholder="Ej. NP300 2024"
                            :class="FIELD"
                            :aria-invalid="Boolean(form.errors.model)"
                        />
                        <Truck :class="ICON" />
                    </div>
                    <p v-if="form.errors.model" :class="ERROR">{{ form.errors.model }}</p>
                </div>

                <div>
                    <label for="unit-serial" :class="LABEL">Número de serie</label>
                    <div class="relative">
                        <input
                            id="unit-serial"
                            v-model="form.serial_number"
                            type="text"
                            autocomplete="off"
                            placeholder="Ej. 3N6AD33A8RK812345"
                            :class="FIELD"
                            :aria-invalid="Boolean(form.errors.serial_number)"
                        />
                        <ScanLine :class="ICON" />
                    </div>
                    <p v-if="form.errors.serial_number" :class="ERROR">{{ form.errors.serial_number }}</p>
                </div>

                <div>
                    <label for="unit-plate" :class="LABEL">Placa</label>
                    <div class="relative">
                        <input
                            id="unit-plate"
                            v-model="form.plate"
                            type="text"
                            autocomplete="off"
                            placeholder="Ej. ABC-12-34"
                            :class="FIELD"
                            :aria-invalid="Boolean(form.errors.plate)"
                        />
                        <Hash :class="ICON" />
                    </div>
                    <p v-if="form.errors.plate" :class="ERROR">{{ form.errors.plate }}</p>
                </div>

                <div>
                    <label for="unit-economic" :class="LABEL">Número económico</label>
                    <div class="relative">
                        <input
                            id="unit-economic"
                            v-model="form.economic_number"
                            type="text"
                            autocomplete="off"
                            placeholder="Ej. U-014"
                            :class="FIELD"
                            :aria-invalid="Boolean(form.errors.economic_number)"
                        />
                        <Hash :class="ICON" />
                    </div>
                    <p v-if="form.errors.economic_number" :class="ERROR">{{ form.errors.economic_number }}</p>
                </div>

                <div>
                    <label for="unit-responsible" :class="LABEL">Responsable</label>
                    <div class="relative">
                        <input
                            id="unit-responsible"
                            v-model="form.responsible"
                            type="text"
                            autocomplete="off"
                            placeholder="Ej. Juan Pérez"
                            :class="FIELD"
                            :aria-invalid="Boolean(form.errors.responsible)"
                        />
                        <User :class="ICON" />
                    </div>
                    <p v-if="form.errors.responsible" :class="ERROR">{{ form.errors.responsible }}</p>
                </div>

                <div>
                    <label for="unit-status" :class="LABEL">
                        Estado
                        <span class="font-normal text-red-500 dark:text-red-400">*</span>
                    </label>
                    <!-- Sin opción vacía: la unidad siempre tiene estado -->
                    <FormSelect
                        id="unit-status"
                        v-model="form.status"
                        :options="statusOptions"
                        :invalid="Boolean(form.errors.status)"
                    />
                    <p v-if="form.errors.status" :class="ERROR">{{ form.errors.status }}</p>
                </div>

                <!-- Endoso: sí o no, sin folio aparte -->
                <div class="sm:col-span-2 lg:col-span-2">
                    <span :class="LABEL">Endoso</span>
                    <label
                        class="flex h-9 cursor-pointer items-center gap-2.5 rounded-lg border border-slate-200 bg-slate-50/70 px-3 text-[0.8rem] text-slate-700 transition-colors duration-150 hover:border-slate-300 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-200 dark:hover:border-white/20"
                    >
                        <input
                            v-model="form.usa_canada_endorsement"
                            type="checkbox"
                            class="size-4 cursor-pointer rounded border-slate-300 text-brand focus:ring-brand/25 dark:border-white/20 dark:bg-white/10"
                        />
                        Cobertura USA / Canadá
                    </label>
                    <p v-if="form.errors.usa_canada_endorsement" :class="ERROR">{{ form.errors.usa_canada_endorsement }}</p>
                </div>
            </div>
        </section>

        <!-- Pagos semestrales -->
        <section :class="CARD">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <p :class="SECTION_TITLE">Pagos semestrales</p>
                <p class="text-[0.7rem] text-slate-400 dark:text-brand-gray/70">
                    El periodo es libre: puede ir de enero a junio o de diciembre a mayo del año siguiente.
                </p>
            </div>

            <div class="mt-3 grid gap-4 lg:grid-cols-2">
                <!-- Mismos tres campos por semestre; solo cambia a cuál pertenecen -->
                <div class="rounded-xl bg-slate-50/70 p-3 ring-1 ring-inset ring-slate-200/70 dark:bg-white/[0.03] dark:ring-white/[0.06]">
                    <!-- Encabezado: qué semestre es, el periodo que llevas capturado y cuánto falta -->
                    <div class="mb-2.5 flex flex-wrap items-center gap-x-2 gap-y-1">
                        <span
                            class="grid size-5 shrink-0 place-content-center rounded-md bg-brand/[0.08] text-[0.6rem] font-bold text-brand dark:bg-white/10 dark:text-white"
                        >
                            1
                        </span>
                        <p class="text-[0.75rem] font-bold text-slate-700 dark:text-slate-200">Primer pago</p>
                        <span class="text-[0.7rem] text-slate-400 dark:text-brand-gray/70">{{ firstPeriod }}</span>
                        <span
                            v-if="firstCountdown"
                            class="ml-auto inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[0.68rem] font-bold whitespace-nowrap ring-1 ring-inset"
                            :class="firstCountdown.tone"
                        >
                            <CalendarClock class="size-3" />
                            {{ firstCountdown.label }}
                        </span>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <label for="unit-p1-start" :class="LABEL">Desde</label>
                            <div class="relative">
                                <input id="unit-p1-start" v-model="form.first_payment_starts_on" type="date" :class="FIELD" />
                                <CalendarDays :class="ICON" />
                            </div>
                            <p v-if="form.errors.first_payment_starts_on" :class="ERROR">{{ form.errors.first_payment_starts_on }}</p>
                        </div>

                        <div>
                            <label for="unit-p1-end" :class="LABEL">Hasta</label>
                            <div class="relative">
                                <input id="unit-p1-end" v-model="form.first_payment_ends_on" type="date" :class="FIELD" />
                                <CalendarDays :class="ICON" />
                            </div>
                            <p v-if="form.errors.first_payment_ends_on" :class="ERROR">{{ form.errors.first_payment_ends_on }}</p>
                        </div>

                        <div>
                            <label for="unit-p1-amount" :class="LABEL">Importe</label>
                            <div class="relative">
                                <input
                                    id="unit-p1-amount"
                                    v-model="form.first_payment_amount"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    placeholder="0.00"
                                    :class="[FIELD, 'tabular-nums']"
                                />
                                <CircleDollarSign :class="ICON" />
                            </div>
                            <p v-if="form.errors.first_payment_amount" :class="ERROR">{{ form.errors.first_payment_amount }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl bg-slate-50/70 p-3 ring-1 ring-inset ring-slate-200/70 dark:bg-white/[0.03] dark:ring-white/[0.06]">
                    <div class="mb-2.5 flex flex-wrap items-center gap-x-2 gap-y-1">
                        <span
                            class="grid size-5 shrink-0 place-content-center rounded-md bg-brand/[0.08] text-[0.6rem] font-bold text-brand dark:bg-white/10 dark:text-white"
                        >
                            2
                        </span>
                        <p class="text-[0.75rem] font-bold text-slate-700 dark:text-slate-200">Segundo pago</p>
                        <span class="text-[0.7rem] text-slate-400 dark:text-brand-gray/70">{{ secondPeriod }}</span>
                        <span
                            v-if="secondCountdown"
                            class="ml-auto inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[0.68rem] font-bold whitespace-nowrap ring-1 ring-inset"
                            :class="secondCountdown.tone"
                        >
                            <CalendarClock class="size-3" />
                            {{ secondCountdown.label }}
                        </span>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <label for="unit-p2-start" :class="LABEL">Desde</label>
                            <div class="relative">
                                <input id="unit-p2-start" v-model="form.second_payment_starts_on" type="date" :class="FIELD" />
                                <CalendarDays :class="ICON" />
                            </div>
                            <p v-if="form.errors.second_payment_starts_on" :class="ERROR">{{ form.errors.second_payment_starts_on }}</p>
                        </div>

                        <div>
                            <label for="unit-p2-end" :class="LABEL">Hasta</label>
                            <div class="relative">
                                <input id="unit-p2-end" v-model="form.second_payment_ends_on" type="date" :class="FIELD" />
                                <CalendarDays :class="ICON" />
                            </div>
                            <p v-if="form.errors.second_payment_ends_on" :class="ERROR">{{ form.errors.second_payment_ends_on }}</p>
                        </div>

                        <div>
                            <label for="unit-p2-amount" :class="LABEL">Importe</label>
                            <div class="relative">
                                <input
                                    id="unit-p2-amount"
                                    v-model="form.second_payment_amount"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    placeholder="0.00"
                                    :class="[FIELD, 'tabular-nums']"
                                />
                                <CircleDollarSign :class="ICON" />
                            </div>
                            <p v-if="form.errors.second_payment_amount" :class="ERROR">{{ form.errors.second_payment_amount }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!--
                Solo informa: el servidor vuelve a calcular lo mismo al guardar. Los importes
                ya traen IVA, así que el costo anual es la suma tal cual y el impuesto se
                desglosa hacia adentro; subtotal más IVA da el costo anual.
            -->
            <dl class="mt-3 grid gap-2 sm:grid-cols-3">
                <div class="rounded-xl bg-slate-50/70 px-3 py-2 dark:bg-white/[0.03]">
                    <dt class="text-[0.62rem] font-bold uppercase tracking-[0.14em] text-slate-400 dark:text-brand-gray/80">Subtotal</dt>
                    <dd class="text-sm font-bold text-slate-800 tabular-nums dark:text-white">{{ money(subtotal) }}</dd>
                </div>
                <div class="rounded-xl bg-slate-50/70 px-3 py-2 dark:bg-white/[0.03]">
                    <dt class="text-[0.62rem] font-bold uppercase tracking-[0.14em] text-slate-400 dark:text-brand-gray/80">IVA incluido (16%)</dt>
                    <dd class="text-sm font-bold text-slate-800 tabular-nums dark:text-white">{{ money(tax) }}</dd>
                </div>
                <div class="rounded-xl bg-brand/[0.07] px-3 py-2 dark:bg-white/10">
                    <dt class="text-[0.62rem] font-bold uppercase tracking-[0.14em] text-brand/70 dark:text-brand-gray/80">Costo anual</dt>
                    <dd class="text-sm font-bold text-brand tabular-nums dark:text-white">{{ money(annualCost) }}</dd>
                </div>
            </dl>
        </section>

        <!-- Evidencias y comentarios -->
        <section :class="CARD">
            <p :class="SECTION_TITLE">Evidencias y comentarios</p>

            <div class="mt-3 grid gap-4 lg:grid-cols-2">
                <div>
                    <span :class="LABEL">Evidencias</span>

                    <!-- Las que ya están guardadas: se marcan para quitar y se puede deshacer -->
                    <ul v-if="unit?.evidences?.length" class="mb-2 flex flex-col gap-1.5">
                        <li
                            v-for="evidence in unit.evidences"
                            :key="evidence.id"
                            class="flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-[0.75rem]"
                            :class="
                                savedEvidences.includes(evidence)
                                    ? 'bg-slate-50 dark:bg-white/[0.04]'
                                    : 'bg-red-50/70 line-through opacity-60 dark:bg-red-500/10'
                            "
                        >
                            <FileText class="size-3.5 shrink-0 text-slate-400 dark:text-brand-gray" />
                            <span class="min-w-0 flex-1 truncate text-slate-700 dark:text-slate-200" :title="evidence.name">{{ evidence.name }}</span>
                            <span class="shrink-0 tabular-nums text-slate-400 dark:text-brand-gray/70">{{ fileSize(evidence.size ?? 0) }}</span>
                            <button
                                v-if="savedEvidences.includes(evidence)"
                                type="button"
                                class="grid size-5 shrink-0 cursor-pointer place-content-center rounded text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10 dark:hover:text-red-300"
                                :aria-label="`Quitar ${evidence.name}`"
                                @click="removeSaved(evidence.id)"
                            >
                                <X class="size-3.5" />
                            </button>
                            <button
                                v-else
                                type="button"
                                class="grid size-5 shrink-0 cursor-pointer place-content-center rounded text-slate-400 hover:bg-slate-200 hover:text-slate-700 dark:hover:bg-white/10 dark:hover:text-white"
                                :aria-label="`Conservar ${evidence.name}`"
                                @click="restoreSaved(evidence.id)"
                            >
                                <RotateCcw class="size-3.5" />
                            </button>
                        </li>
                    </ul>

                    <button
                        type="button"
                        class="flex w-full cursor-pointer flex-col items-center gap-1 rounded-xl border border-dashed border-slate-300 bg-slate-50/70 px-4 py-5 text-center transition-colors duration-150 hover:border-brand/40 hover:bg-brand/[0.03] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/15 dark:border-white/15 dark:bg-white/[0.03] dark:hover:border-white/30 dark:hover:bg-white/[0.06]"
                        @click="fileInput?.click()"
                    >
                        <Paperclip class="size-4 text-slate-400 dark:text-brand-gray" />
                        <span class="text-[0.78rem] font-semibold text-slate-700 dark:text-slate-200">Elegir archivos</span>
                        <span class="text-[0.7rem] text-slate-400 dark:text-brand-gray/70">
                            PDF, imágenes u Office · hasta 10 archivos de 10 MB
                            <template v-if="editing"> · se suman a las que ya tiene</template>
                        </span>
                    </button>

                    <input
                        ref="fileInput"
                        type="file"
                        multiple
                        accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx"
                        class="hidden"
                        @change="addFiles"
                    />

                    <ul v-if="form.evidences.length" class="mt-2 flex flex-col gap-1.5">
                        <li
                            v-for="(file, index) in form.evidences"
                            :key="`${file.name}-${index}`"
                            class="flex items-center gap-2 rounded-lg bg-slate-50 px-2.5 py-1.5 text-[0.75rem] dark:bg-white/[0.04]"
                        >
                            <FileText class="size-3.5 shrink-0 text-slate-400 dark:text-brand-gray" />
                            <span class="min-w-0 flex-1 truncate text-slate-700 dark:text-slate-200" :title="file.name">{{ file.name }}</span>
                            <span class="shrink-0 tabular-nums text-slate-400 dark:text-brand-gray/70">{{ fileSize(file.size) }}</span>
                            <button
                                type="button"
                                class="grid size-5 shrink-0 cursor-pointer place-content-center rounded text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10 dark:hover:text-red-300"
                                :aria-label="`Quitar ${file.name}`"
                                @click="removeFile(index)"
                            >
                                <X class="size-3.5" />
                            </button>
                        </li>
                    </ul>

                    <!-- Los errores llegan por archivo: evidences.0, evidences.1… -->
                    <template v-for="(error, key) in form.errors" :key="key">
                        <p v-if="String(key).startsWith('evidences')" :class="ERROR">{{ error }}</p>
                    </template>
                </div>

                <div>
                    <label for="unit-comments" :class="LABEL">Comentarios / financiamiento</label>
                    <div class="relative">
                        <textarea
                            id="unit-comments"
                            v-model="form.comments"
                            rows="6"
                            placeholder="Arrendadora, plazo, número de contrato, observaciones…"
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
                href="/flotillas"
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
                {{ editing ? 'Guardar cambios' : 'Guardar unidad' }}
            </button>
        </div>
    </form>
</template>
