<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { Building2, FileText, Hash, Loader2, MessageSquareText, Paperclip, RotateCcw, Save, ScanLine, Truck, User, X } from 'lucide-vue-next';
import { computed, nextTick, ref } from 'vue';
import FormSelect from '@/components/app/FormSelect.vue';
import { UNIT_STATUS } from '@/lib/units';

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
    business_unit_id: props.unit?.business_unit_id ?? '',
    brand: (props.unit?.brand ?? '').toUpperCase(),
    model: (props.unit?.model ?? '').toUpperCase(),
    serial_number: props.unit?.serial_number ?? '',
    plate: (props.unit?.plate ?? '').toUpperCase(),
    economic_number: props.unit?.economic_number ?? '',
    responsible: (props.unit?.responsible ?? '').toUpperCase(),
    status: props.unit?.status ?? 'active',
    comments: props.unit?.comments ?? '',
    evidences: [],
    // Ids de las evidencias que ya estaban y se quitan al guardar.
    remove_evidences: [],
});

/* ---------- Mayúsculas ---------- */

/**
 * Marca, modelo, placa y responsable van siempre en mayúsculas, tenga o no
 * activo el bloq mayús: se convierten mientras escribe. El cursor se deja
 * donde estaba para que corregir a media palabra no lo mande al final.
 */
function toUpper(field, event) {
    const input = event.target;
    const { selectionStart, selectionEnd } = input;

    form[field] = input.value.toUpperCase();
    nextTick(() => input.setSelectionRange(selectionStart, selectionEnd));
}

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

const SECTION_TITLE = 'text-[0.62rem] font-bold uppercase tracking-[0.14em] text-slate-400 dark:text-brand-gray/80';
</script>

<template>
    <form id="unit-form" class="flex flex-col gap-3 tall:gap-4" novalidate @submit.prevent="submit">
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
                    <label for="unit-plate" :class="LABEL">Placa</label>
                    <div class="relative">
                        <input
                            id="unit-plate"
                            :value="form.plate"
                            @input="toUpper('plate', $event)"
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
                            :value="form.responsible"
                            @input="toUpper('responsible', $event)"
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

        <!-- Evidencias y comentarios -->
        <section :class="CARD">
            <p :class="SECTION_TITLE">Documentos y comentarios</p>

            <div class="mt-3 grid gap-4 lg:grid-cols-2">
                <div>
                    <span :class="LABEL">Documentos oficiales</span>
                    <p class="-mt-1 mb-2 text-[0.7rem] text-slate-400 dark:text-brand-gray/70">
                        Tarjeta de circulación, factura…
                    </p>

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
                            <template v-if="editing"> · se suman a los que ya tiene</template>
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
