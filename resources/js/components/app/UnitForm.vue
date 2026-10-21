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
    RotateCcw,
    Save,
    ScanLine,
    ShieldCheck,
    Truck,
    User,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import FilePreviewDialog from '@/components/app/FilePreviewDialog.vue';
import FormSelect from '@/components/app/FormSelect.vue';
import FormSwitch from '@/components/app/FormSwitch.vue';
import PolicySourceFields from '@/components/app/PolicySourceFields.vue';
import { catalogOptions } from '@/lib/catalogs';
import { POLICY_COVERAGES } from '@/lib/coverages';
import { ACCEPT, FILE_KINDS, extensionOf, fileSize, kindOf } from '@/lib/files';
import { SEMESTER_MONTHS, UNIT_STATUS, addMonths, fleetListUrl, money, periodLabel } from '@/lib/units';

/**
 * El formulario de la unidad con su póliza vehicular, el mismo para el alta y
 * la edición: solo cambia de dónde salen los valores iniciales y a qué ruta se
 * envía. La póliza es obligatoria; aquí no se registran pagos.
 *
 * La empresarial dura un año en dos semestres; la del proveedor trae su
 * propia vigencia y un pago único anual (en los campos del primer pago).
 */
const props = defineProps({
    /** null = alta · unidad cargada (con su póliza vigente) = edición. */
    unit: { type: Object, default: null },
    /** Catálogo: [{ id, name }]. Puede venir vacío si nadie lo ha llenado. */
    businessUnits: { type: Array, default: () => [] },
    /** Catálogos: [{ id, name }]. */
    insurers: { type: Array, default: () => [] },
    beneficiaries: { type: Array, default: () => [] },
    /** Valores del enum `status`, tal como los acepta el servidor. */
    statuses: { type: Array, default: () => [] },
    /** Coberturas de la póliza vehicular, del enum `coverage`. */
    coverages: { type: Array, default: () => [] },
    /** En el alta: con qué origen abre la póliza. */
    initialSource: { type: String, default: 'corporate' },
});

/** IVA incluido en los importes. Igual que UnitPolicy::TAX_RATE. */
const TAX_RATE = 0.16;

const editing = computed(() => props.unit !== null);

/** La preferida del catálogo (la que ganó la subasta): la de las empresariales. */
function preferredInsurer() {
    return props.insurers.find((insurer) => insurer.preferred)?.id ?? '';
}

const form = useForm({
    business_unit_id: props.unit?.business_unit_id ?? '',
    // El tipo se elige antes de abrir el formulario: aquí siempre es automóvil,
    // salvo las máquinas que se capturaron antes con este formulario.
    type: props.unit?.type ?? 'vehicle',
    brand: (props.unit?.brand ?? '').toUpperCase(),
    model: (props.unit?.model ?? '').toUpperCase(),
    serial_number: props.unit?.serial_number ?? '',
    plate: (props.unit?.plate ?? '').toUpperCase(),
    economic_number: props.unit?.economic_number ?? '',
    responsible: (props.unit?.responsible ?? '').toUpperCase(),
    status: props.unit?.status ?? 'active',
    comments: props.unit?.comments ?? '',
    // La póliza vigente.
    policy: (props.unit?.policy ?? '').toUpperCase(),
    certificate: (props.unit?.certificate ?? '').toUpperCase(),
    // Las empresariales abren con la preferida; se puede cambiar.
    insurer_id: props.unit?.insurer_id ?? (props.initialSource === 'supplier' ? '' : preferredInsurer()),
    coverage: props.unit?.coverage ?? '',
    endorsement: props.unit?.endorsement ?? false,
    financing: props.unit?.financing ?? props.initialSource === 'supplier',
    valid_from: props.unit?.valid_from ?? '',
    valid_until: props.unit?.source === 'supplier' ? (props.unit?.valid_until ?? '') : '',
    source: props.unit?.source ?? props.initialSource,
    supplier_id: props.unit?.supplier_id ?? '',
    first_payment_amount: props.unit?.first_payment_amount ?? '',
    second_payment_amount: props.unit?.second_payment_amount ?? '',
    evidences: [],
    // Ids de las evidencias que ya estaban y se quitan al guardar.
    remove_evidences: [],
});

/* ---------- Póliza ---------- */

const coverageOptions = computed(() => props.coverages.map((value) => ({ value, label: POLICY_COVERAGES[value] ?? value })));

const fromSupplier = computed(() => form.source === 'supplier');

/**
 * La preferida del catálogo es la aseguradora de las empresariales; con
 * beneficiario preferente la elige el proveedor. Al prender el beneficiario se
 * deja libre; al apagarlo vuelve a la preferida, aunque se haya elegido otra.
 * Después se puede cambiar.
 */
watch(
    () => form.source,
    (source) => {
        if (source === 'supplier' && String(form.insurer_id) === String(preferredInsurer())) form.insurer_id = '';
        if (source === 'corporate') form.insurer_id = preferredInsurer();
    },
);

/** Pesos con centavos, sin arrastrar decimales de la división. */
const cents = (amount) => Math.round(amount * 100) / 100;

/**
 * Al cambiar de origen se pasa lo capturado al otro esquema:
 *  - A preferente: la vigencia corre del inicio del primer semestre al fin del
 *    segundo y el pago único es la suma de los dos semestres.
 *  - A empresarial: los semestres se calculan desde el inicio (un año, aunque
 *    la preferente durara más) y el pago único se parte en dos.
 */
watch(
    () => form.source,
    (source) => {
        const first = Number(form.first_payment_amount) || 0;
        const second = Number(form.second_payment_amount) || 0;

        if (source === 'supplier') {
            form.valid_until = semesters.value ? semesters.value.second.ends_on : '';
            form.first_payment_amount = first || second ? cents(first + second) : '';
            form.second_payment_amount = '';

            return;
        }

        form.valid_until = '';

        if (first) {
            form.first_payment_amount = cents(first / 2);
            form.second_payment_amount = cents(first - cents(first / 2));
        }
    },
);

/** Los dos interruptores de la póliza: se capturan igual. */
const SWITCHES = {
    financing: { label: 'Financiamiento', on: 'Con financiamiento', off: 'Sin financiamiento' },
    endorsement: { label: 'Endoso', on: 'Con endoso USA/Canada', off: 'Sin endoso USA/Canada' },
};

/** Los dos semestres, como los calcula el servidor. */
const semesters = computed(() => {
    if (!form.valid_from) return null;

    const middle = addMonths(form.valid_from, SEMESTER_MONTHS);

    return {
        first: { starts_on: form.valid_from, ends_on: middle },
        second: { starts_on: middle, ends_on: addMonths(middle, SEMESTER_MONTHS) },
    };
});

/** Costo anual: los dos semestres, o en la del proveedor su pago único (en el primero). */
const total = computed(() => (Number(form.first_payment_amount) || 0) + (fromSupplier.value ? 0 : Number(form.second_payment_amount) || 0));

const tax = computed(() => Math.round(((total.value * TAX_RATE) / (1 + TAX_RATE)) * 100) / 100);

const SEMESTERS = [
    { key: 'first', title: 'Primer semestre' },
    { key: 'second', title: 'Segundo semestre' },
];

/* ---------- Mayúsculas ---------- */

/**
 * Marca, modelo, placa, asignación, póliza, certificado y aseguradora van
 * siempre en mayúsculas, tenga o no activo el bloq mayús: se convierten
 * mientras escribe. El cursor se deja donde estaba para que corregir a media
 * palabra no lo mande al final.
 */
function toUpper(field, event) {
    const input = event.target;
    const { selectionStart, selectionEnd } = input;

    form[field] = input.value.toUpperCase();
    nextTick(() => input.setSelectionRange(selectionStart, selectionEnd));
}

/* ---------- Evidencias ---------- */

const fileInput = ref(null);

/** Miniatura de las imágenes nuevas; se liberan al quitarlas o al salir. */
const previews = new Map();

onBeforeUnmount(() => previews.forEach((url) => URL.revokeObjectURL(url)));

/** Los que el selector dejó pasar («Todos los archivos») pero no se aceptan. */
const rejected = ref([]);

/** Los que ya estaban en la lista y no se volvieron a sumar. */
const duplicated = ref([]);

/**
 * El mismo archivo es el mismo nombre con el mismo peso. Cuenta contra los
 * nuevos y contra los guardados que se conservan: uno marcado para quitar
 * se puede volver a subir.
 */
function fingerprint(name, size) {
    return `${name.toLowerCase()}|${size}`;
}

function addFiles(event) {
    const picked = Array.from(event.target.files);
    const taken = new Set([
        ...(props.unit?.evidences ?? [])
            .filter((evidence) => !form.remove_evidences.includes(evidence.id))
            .map((evidence) => fingerprint(evidence.name, evidence.size ?? 0)),
        ...form.evidences.map((file) => fingerprint(file.name, file.size)),
    ]);

    const accepted = [];
    rejected.value = [];
    duplicated.value = [];

    for (const file of picked) {
        const key = fingerprint(file.name, file.size);

        if (!kindOf(file.name)) {
            rejected.value.push(file.name);
        } else if (taken.has(key)) {
            duplicated.value.push(file.name);
        } else {
            // También evita repetirlo si lo eligió dos veces en la misma selección.
            taken.add(key);
            accepted.push(file);
        }
    }

    accepted
        .filter((file) => kindOf(file.name) === 'image')
        .forEach((file) => previews.set(file, URL.createObjectURL(file)));

    // Se suman a las que ya eligió: abrir el selector otra vez no borra lo anterior.
    form.evidences = [...form.evidences, ...accepted];

    // El input se limpia para poder volver a elegir el mismo archivo.
    event.target.value = '';
}

function removeFile(index) {
    const file = form.evidences[index];

    if (previews.has(file)) {
        URL.revokeObjectURL(previews.get(file));
        previews.delete(file);
    }

    form.evidences = form.evidences.filter((_, i) => i !== index);
}

/** Marcar y desmarcar: nada se borra en el servidor hasta guardar. */
function removeSaved(id) {
    form.remove_evidences = [...form.remove_evidences, id];
}

function restoreSaved(id) {
    form.remove_evidences = form.remove_evidences.filter((value) => value !== id);
}

/**
 * Guardadas y nuevas en una sola fila de tarjetas. Las guardadas que se
 * marcaron para quitar siguen ahí, apagadas, para poder deshacerlo.
 */
const fileCards = computed(() =>
    [
        ...(props.unit?.evidences ?? []).map((evidence) => ({
            key: `saved-${evidence.id}`,
            saved: true,
            id: evidence.id,
            name: evidence.name,
            size: evidence.size ?? 0,
            removed: form.remove_evidences.includes(evidence.id),
        })),
        ...form.evidences.map((file, index) => ({
            key: `new-${index}-${file.name}`,
            saved: false,
            index,
            name: file.name,
            size: file.size,
            preview: previews.get(file),
            // Los errores llegan por archivo: evidences.0, evidences.1…
            error: Boolean(form.errors[`evidences.${index}`]),
        })),
    ].map((card) => {
        const extension = extensionOf(card.name);

        return { ...card, extension: extension.toUpperCase(), kind: FILE_KINDS[kindOf(card.name)] ?? FILE_KINDS.pdf };
    }),
);

function toggleCard(card) {
    if (!card.saved) return removeFile(card.index);

    card.removed ? restoreSaved(card.id) : removeSaved(card.id);
}

/* ---------- Vista previa ---------- */

const previewOpen = ref(false);
const previewFile = ref(null);

/** El nuevo se ve desde memoria; el guardado se trae de su ruta de descarga. */
function openPreview(card) {
    previewFile.value = {
        name: card.name,
        size: card.size,
        source: card.saved ? `/flotillas/${props.unit.id}/evidencias/${card.id}` : form.evidences[card.index],
    };
    previewOpen.value = true;
}

/* ---------- Envío ---------- */

// Con su punto de color: el estado se reconoce antes de leerlo.
const statusOptions = computed(() =>
    props.statuses.map((value) => ({ value, label: (UNIT_STATUS[value]?.label ?? value).toUpperCase(), dot: UNIT_STATUS[value]?.dot })),
);

const insurerOptions = computed(() => catalogOptions(props.insurers));

const businessUnitOptions = computed(() => props.businessUnits.map(({ id, name }) => ({ value: id, label: name.toUpperCase() })));

function submit() {
    // Vacío viaja como null para que la base guarde «sin dato» y no una cadena
    // vacía.
    form.transform((data) => ({
        ...Object.fromEntries(Object.entries(data).map(([key, value]) => [key, value === '' ? null : value])),
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
    'dark:focus:border-brand-gray/50 dark:focus:bg-white/[0.06] dark:focus:ring-white/10 dark:[color-scheme:dark] ' +
    'disabled:cursor-not-allowed disabled:opacity-60';

const ICON =
    'pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-slate-400 transition-colors ' +
    'peer-focus:text-brand dark:text-white/35 dark:peer-focus:text-white';

const LABEL = 'mb-1.5 block text-[0.75rem] font-semibold text-slate-700 dark:text-slate-200';

const ERROR = 'mt-1 text-[0.7rem] font-medium text-red-600 dark:text-red-400';

const CARD =
    'rounded-2xl border border-slate-200/80 bg-white p-4 shadow-[0_1px_3px_rgb(2_29_73/0.04),0_8px_24px_-12px_rgb(2_29_73/0.08)] ' +
    'tall:p-5 dark:border-white/[0.08] dark:bg-brand-deep dark:shadow-none';

const SECTION_TITLE = 'text-[0.62rem] font-bold text-slate-400 dark:text-brand-gray/80';
</script>

<template>
    <form id="unit-form" class="flex flex-col gap-3 tall:gap-4" novalidate @submit.prevent="submit">
        <!-- Vehículo -->
        <section :class="CARD">
            <p :class="SECTION_TITLE">Unidad</p>

            <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="unit-brand" :class="LABEL">
                        Marca
                        <span class="font-normal text-red-500 dark:text-red-400">*</span>
                    </label>
                    <div class="relative">
                        <input
                            id="unit-brand"
                            :value="form.brand"
                            @input="toUpper('brand', $event)"
                            type="text"
                            required
                            autofocus
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
                            :value="form.model"
                            @input="toUpper('model', $event)"
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
                    <label for="unit-plate" :class="LABEL">
                        Placa
                        <span class="font-normal text-red-500 dark:text-red-400">*</span>
                    </label>
                    <div class="relative">
                        <input
                            id="unit-plate"
                            :value="form.plate"
                            @input="toUpper('plate', $event)"
                            type="text"
                            required
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
                    <label for="unit-responsible" :class="LABEL">
                        Asignación
                        <span class="font-normal text-red-500 dark:text-red-400">*</span>
                    </label>
                    <div class="relative">
                        <input
                            id="unit-responsible"
                            :value="form.responsible"
                            @input="toUpper('responsible', $event)"
                            type="text"
                            required
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

                <div>
                    <label for="unit-business" :class="LABEL">
                        Unidad de negocio
                        <span class="font-normal text-red-500 dark:text-red-400">*</span>
                    </label>
                    <FormSelect
                        id="unit-business"
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
            </div>
        </section>

        <!-- Póliza vehicular: obligatoria, va con la unidad -->
        <section :class="CARD">
            <p :class="SECTION_TITLE">Póliza</p>

            <!-- Primero quién manda en la póliza: el financiamiento y, con él, el beneficiario preferente y su proveedor -->
            <PolicySourceFields :form="form" :beneficiaries="beneficiaries" prefix="unit" vehicle class="mt-3">
                <FormSwitch
                    id="unit-financing"
                    v-model="form.financing"
                    :label="SWITCHES.financing.label"
                    :on="SWITCHES.financing.on"
                    :off="SWITCHES.financing.off"
                    :error="form.errors.financing"
                />
            </PolicySourceFields>

            <!-- Luego la póliza: con quién, su número y qué cubre -->
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="unit-insurer" :class="LABEL">Aseguradora</label>
                    <FormSelect
                        id="unit-insurer"
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
                    <label for="unit-policy" :class="LABEL">
                        Número de póliza
                        <span class="font-normal text-red-500 dark:text-red-400">*</span>
                    </label>
                    <div class="relative">
                        <input
                            id="unit-policy"
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
                    <label for="unit-certificate" :class="LABEL">Certificado</label>
                    <div class="relative">
                        <input
                            id="unit-certificate"
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
                    <label for="unit-coverage" :class="LABEL">
                        Cobertura
                        <span class="font-normal text-red-500 dark:text-red-400">*</span>
                    </label>
                    <FormSelect
                        id="unit-coverage"
                        v-model="form.coverage"
                        :options="coverageOptions"
                        :icon="ShieldCheck"
                        placeholder="Selecciona la cobertura"
                        :invalid="Boolean(form.errors.coverage)"
                    />
                    <p v-if="form.errors.coverage" :class="ERROR">{{ form.errors.coverage }}</p>
                </div>

                <FormSwitch
                    id="unit-endorsement"
                    v-model="form.endorsement"
                    :label="SWITCHES.endorsement.label"
                    :on="SWITCHES.endorsement.on"
                    :off="SWITCHES.endorsement.off"
                    :error="form.errors.endorsement"
                />
            </div>
        </section>

        <!-- Del proveedor: su vigencia tal cual, sin semestres, y un pago único anual -->
        <section v-if="fromSupplier" :class="CARD">
            <p :class="SECTION_TITLE">Vigencia y pago</p>

            <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="unit-supplier-from" :class="LABEL">
                        Inicio de vigencia
                        <span class="font-normal text-red-500 dark:text-red-400">*</span>
                    </label>
                    <div class="relative">
                        <input id="unit-supplier-from" v-model="form.valid_from" type="date" required :class="FIELD" :aria-invalid="Boolean(form.errors.valid_from)" />
                        <CalendarDays :class="ICON" />
                    </div>
                    <p v-if="form.errors.valid_from" :class="ERROR">{{ form.errors.valid_from }}</p>
                </div>

                <div>
                    <label for="unit-supplier-until" :class="LABEL">
                        Fin de vigencia
                        <span class="font-normal text-red-500 dark:text-red-400">*</span>
                    </label>
                    <div class="relative">
                        <input
                            id="unit-supplier-until"
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
                    <p v-else class="mt-1 text-[0.7rem] text-slate-400 dark:text-brand-gray/70">La que dé el proveedor: puede ser de más de un año.</p>
                </div>

                <div>
                    <label for="unit-supplier-amount" :class="LABEL">Monto a pagar</label>
                    <div class="relative">
                        <input
                            id="unit-supplier-amount"
                            v-model="form.first_payment_amount"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="0.00"
                            :class="[FIELD, 'tabular-nums']"
                            :aria-invalid="Boolean(form.errors.first_payment_amount)"
                        />
                        <CircleDollarSign :class="ICON" />
                    </div>
                    <p v-if="form.errors.first_payment_amount" :class="ERROR">{{ form.errors.first_payment_amount }}</p>
                    <p v-else class="mt-1 text-[0.7rem] tabular-nums text-slate-400 dark:text-brand-gray/70">
                        <template v-if="total">IVA {{ Math.round(TAX_RATE * 100) }}%: {{ money(tax) }} · Subtotal: {{ money(total - tax) }}</template>
                        <template v-else>Pago único anual, IVA incluido.</template>
                    </p>
                </div>
            </div>
        </section>

        <!-- Vigencia semestral y costo anual -->
        <section v-else :class="CARD">
            <p :class="SECTION_TITLE">Vigencia y costo</p>

            <div class="mt-3 grid gap-4 lg:grid-cols-3">
                <div>
                    <label for="unit-valid-from" :class="LABEL">
                        Inicio de vigencia
                        <span class="font-normal text-red-500 dark:text-red-400">*</span>
                    </label>
                    <div class="relative">
                        <input
                            id="unit-valid-from"
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

                <!-- El costo se calcula de los dos semestres, con IVA incluido -->
                <div class="flex flex-col justify-center rounded-xl bg-slate-50/70 px-3.5 py-2 ring-1 ring-inset ring-slate-200/70 lg:col-span-2 dark:bg-white/[0.03] dark:ring-white/[0.06]">
                    <p class="text-[0.62rem] font-bold text-slate-400 dark:text-brand-gray/80">Costo anual (IVA incluido)</p>
                    <p class="text-lg leading-tight font-bold tabular-nums text-brand dark:text-white">{{ money(total) }}</p>
                    <p class="text-[0.7rem] tabular-nums text-slate-500 dark:text-brand-gray">IVA {{ Math.round(TAX_RATE * 100) }}%: {{ money(tax) }} · Subtotal: {{ money(total - tax) }}</p>
                </div>
            </div>

            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <article
                    v-for="semester in SEMESTERS"
                    :key="semester.key"
                    class="rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2.5 dark:border-white/10 dark:bg-white/[0.02]"
                >
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <p class="text-[0.8rem] font-bold text-slate-800 dark:text-white">{{ semester.title }}</p>
                        <p class="text-[0.7rem] tabular-nums text-slate-500 dark:text-brand-gray">{{ semesters ? periodLabel(semesters[semester.key]) : '—' }}</p>
                    </div>

                    <label :for="`unit-${semester.key}-amount`" :class="LABEL">Importe</label>
                    <div class="relative">
                        <input
                            :id="`unit-${semester.key}-amount`"
                            v-model="form[`${semester.key}_payment_amount`]"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="0.00"
                            :class="[FIELD, 'tabular-nums']"
                            :aria-invalid="Boolean(form.errors[`${semester.key}_payment_amount`])"
                        />
                        <CircleDollarSign :class="ICON" />
                    </div>
                    <p v-if="form.errors[`${semester.key}_payment_amount`]" :class="ERROR">{{ form.errors[`${semester.key}_payment_amount`] }}</p>
                </article>
            </div>
        </section>

        <!-- Evidencias y comentarios -->
        <section :class="CARD">
            <p :class="SECTION_TITLE">Documentos y comentarios</p>

            <div class="mt-3 grid gap-4 lg:grid-cols-2">
                <div>
                    <span :class="LABEL">Documentos oficiales</span>
                    <p class="-mt-1 mb-2 text-[0.7rem] text-slate-400 dark:text-brand-gray/70">
                        Tarjeta de circulación, factura…
                    </p>

                    <button
                        type="button"
                        class="flex h-28 w-full cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border border-dashed border-slate-300 bg-slate-50/70 px-4 text-center transition-colors duration-150 hover:border-brand/40 hover:bg-brand/[0.03] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/15 dark:border-white/15 dark:bg-white/[0.03] dark:hover:border-white/30 dark:hover:bg-white/[0.06]"
                        @click="fileInput?.click()"
                    >
                        <Paperclip class="size-4 text-slate-400 dark:text-brand-gray" />
                        <span class="text-[0.78rem] font-semibold text-slate-700 dark:text-slate-200">Elegir archivos</span>
                        <span class="text-[0.7rem] text-slate-400 dark:text-brand-gray/70">
                            PDF, Word, Excel, imágenes o XML · hasta 10 archivos de 10 MB
                            <template v-if="editing"> · se suman a los que ya tiene</template>
                        </span>
                    </button>

                    <input ref="fileInput" type="file" multiple :accept="ACCEPT" class="hidden" @change="addFiles" />

                    <!-- Una tarjeta por archivo, en fila; las que no caben bajan a la siguiente -->
                    <ul v-if="fileCards.length" class="mt-2 flex flex-wrap gap-2">
                        <li
                            v-for="card in fileCards"
                            :key="card.key"
                            class="relative flex h-28 w-24 flex-col items-center gap-1.5 rounded-xl border px-2 pt-3 pb-2 text-center transition-opacity duration-150"
                            :class="[
                                card.removed
                                    ? 'border-dashed border-red-300 bg-red-50/60 opacity-60 dark:border-red-500/30 dark:bg-red-500/[0.06]'
                                    : card.error
                                      ? 'border-red-400 bg-white ring-2 ring-red-500/15 dark:border-red-400/60 dark:bg-white/[0.03]'
                                      : 'border-slate-200 bg-white dark:border-white/10 dark:bg-white/[0.03]',
                            ]"
                            :title="card.name"
                        >
                            <!-- Toda la tarjeta abre la vista previa; la X queda encima -->
                            <button
                                type="button"
                                class="absolute inset-0 cursor-pointer rounded-xl transition-colors hover:bg-slate-900/[0.03] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/15 dark:hover:bg-white/[0.04]"
                                :aria-label="`Ver ${card.name}`"
                                @click="openPreview(card)"
                            />

                            <!-- Punto de marca: todavía no se sube -->
                            <span
                                v-if="!card.saved"
                                class="pointer-events-none absolute top-1.5 left-1.5 size-1.5 rounded-full bg-brand dark:bg-brand-gray"
                                title="Por subir"
                            />

                            <img
                                v-if="card.preview"
                                :src="card.preview"
                                alt=""
                                class="size-10 shrink-0 rounded-lg object-cover ring-1 ring-slate-200 dark:ring-white/10"
                            />
                            <span v-else class="grid size-10 shrink-0 place-content-center rounded-lg" :class="card.kind.tile">
                                <component :is="card.kind.icon" class="size-5" />
                            </span>

                            <span
                                class="line-clamp-2 w-full text-[0.66rem] leading-tight font-medium break-all text-slate-700 dark:text-slate-200"
                                :class="{ 'line-through': card.removed }"
                            >
                                {{ card.name }}
                            </span>

                            <span class="mt-auto text-[0.6rem] tabular-nums text-slate-400 dark:text-brand-gray/70">
                                <span class="font-bold">{{ card.extension }}</span> · {{ fileSize(card.size) }}
                            </span>

                            <button
                                type="button"
                                class="absolute top-1 right-1 z-[1] grid size-5 cursor-pointer place-content-center rounded text-slate-400 transition-colors"
                                :class="
                                    card.removed
                                        ? 'hover:bg-slate-200 hover:text-slate-700 dark:hover:bg-white/10 dark:hover:text-white'
                                        : 'hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10 dark:hover:text-red-300'
                                "
                                :aria-label="card.removed ? `Conservar ${card.name}` : `Quitar ${card.name}`"
                                @click="toggleCard(card)"
                            >
                                <RotateCcw v-if="card.removed" class="size-3.5" />
                                <X v-else class="size-3.5" />
                            </button>
                        </li>
                    </ul>

                    <p v-if="rejected.length" :class="ERROR">
                        No se agregó {{ rejected.join(', ') }}: solo PDF, Word, Excel, imágenes (JPG, PNG, WEBP) o XML.
                    </p>
                    <p v-if="duplicated.length" :class="ERROR">Ya está en la lista: {{ duplicated.join(', ') }}.</p>

                    <template v-for="(error, key) in form.errors" :key="key">
                        <p v-if="String(key).startsWith('evidences')" :class="ERROR">{{ error }}</p>
                    </template>
                </div>

                <div>
                    <label for="unit-comments" :class="LABEL">Comentarios</label>
                    <div class="relative">
                        <textarea
                            id="unit-comments"
                            v-model="form.comments"
                            rows="6"
                            placeholder="Observaciones de la unidad y su póliza…"
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
                :href="fleetListUrl(unit?.source ?? initialSource)"
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

        <FilePreviewDialog v-model:open="previewOpen" :file="previewFile" />
    </form>
</template>
