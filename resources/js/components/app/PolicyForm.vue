<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import {
    Building2,
    CalendarCheck,
    CalendarDays,
    CircleDollarSign,
    FileCheck,
    HardHat,
    Landmark,
    Loader2,
    MapPin,
    MessageSquareText,
    Paperclip,
    RotateCcw,
    Save,
    ShieldCheck,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import FilePreviewDialog from '@/components/app/FilePreviewDialog.vue';
import FormSelect from '@/components/app/FormSelect.vue';
import { catalogOptions } from '@/lib/catalogs';
import { POLICY_COVERAGES } from '@/lib/coverages';
import { ACCEPT, FILE_KINDS, extensionOf, fileSize, kindOf } from '@/lib/files';
import { money } from '@/lib/units';

/**
 * El formulario de la póliza de obra, el mismo para el alta y la edición. La
 * vigencia se captura completa: el fin es la terminación de la obra o, en
 * algunos casos, un año exacto. Con endosos el fin solo se ve: lo mueve el
 * último endoso.
 *
 * Las vehiculares y las de maquinaria no se capturan aquí: van en Flotillas.
 */
const props = defineProps({
    /** null = alta · póliza cargada = edición. */
    policy: { type: Object, default: null },
    /** Valores del enum `coverage` de las de obra. */
    coverages: { type: Array, default: () => [] },
    /** Catálogo: [{ id, name }]. */
    businessUnits: { type: Array, default: () => [] },
    /** Catálogo: [{ id, name }]. */
    insurers: { type: Array, default: () => [] },
});

/** IVA incluido en el monto. Igual que ConstructionPolicy::TAX_RATE. */
const TAX_RATE = 0.16;

const editing = computed(() => props.policy !== null);

/** Con endosos, el fin de vigencia no se edita aquí. */
const endorsed = computed(() => Boolean(props.policy?.endorsed));

const form = useForm({
    project: (props.policy?.project ?? '').toUpperCase(),
    project_address: (props.policy?.project_address ?? '').toUpperCase(),
    business_unit_id: props.policy?.business_unit_id ?? '',
    policy: (props.policy?.policy ?? '').toUpperCase(),
    insurer_id: props.policy?.insurer_id ?? '',
    coverage: props.policy?.coverage ?? '',
    valid_from: props.policy?.valid_from ?? '',
    valid_until: props.policy?.valid_until ?? '',
    amount: props.policy?.amount ?? '',
    comments: (props.policy?.comments ?? '').toUpperCase(),
    documents: [],
    remove_documents: [],
});

/* ---------- Mayúsculas ---------- */

/**
 * Póliza, aseguradora, obra, dirección y comentarios van siempre en mayúsculas: se convierten
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

const coverageOptions = computed(() => props.coverages.map((value) => ({ value, label: POLICY_COVERAGES[value] ?? value })));

const insurerOptions = computed(() => catalogOptions(props.insurers));

const businessUnitOptions = computed(() => props.businessUnits.map(({ id, name }) => ({ value: id, label: name.toUpperCase() })));

/* ---------- Vigencia y monto ---------- */

const total = computed(() => Number(form.amount) || 0);

const tax = computed(() => Math.round(((total.value * TAX_RATE) / (1 + TAX_RATE)) * 100) / 100);

/* ---------- Documentos (igual que en Flotillas) ---------- */

const fileInput = ref(null);

/** Miniatura de las imágenes nuevas; se liberan al quitarlas o al salir. */
const previews = new Map();

onBeforeUnmount(() => previews.forEach((url) => URL.revokeObjectURL(url)));

/** Los que el selector dejó pasar («Todos los archivos») pero no se aceptan. */
const rejected = ref([]);

/** Los que ya estaban en la lista y no se volvieron a sumar. */
const duplicated = ref([]);

/** El mismo archivo es el mismo nombre con el mismo peso. */
function fingerprint(name, size) {
    return `${name.toLowerCase()}|${size}`;
}

function addFiles(event) {
    const picked = Array.from(event.target.files);
    const taken = new Set([
        ...(props.policy?.documents ?? [])
            .filter((document) => !form.remove_documents.includes(document.id))
            .map((document) => fingerprint(document.name, document.size ?? 0)),
        ...form.documents.map((file) => fingerprint(file.name, file.size)),
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
            taken.add(key);
            accepted.push(file);
        }
    }

    accepted
        .filter((file) => kindOf(file.name) === 'image')
        .forEach((file) => previews.set(file, URL.createObjectURL(file)));

    // Se suman a los que ya eligió: abrir el selector otra vez no borra lo anterior.
    form.documents = [...form.documents, ...accepted];

    // El input se limpia para poder volver a elegir el mismo archivo.
    event.target.value = '';
}

function removeFile(index) {
    const file = form.documents[index];

    if (previews.has(file)) {
        URL.revokeObjectURL(previews.get(file));
        previews.delete(file);
    }

    form.documents = form.documents.filter((_, i) => i !== index);
}

/**
 * Guardados y nuevos en una sola fila de tarjetas. Los guardados que se
 * marcaron para quitar siguen ahí, apagados, para poder deshacerlo: nada se
 * borra en el servidor hasta guardar.
 */
const fileCards = computed(() =>
    [
        ...(props.policy?.documents ?? []).map((document) => ({
            key: `saved-${document.id}`,
            saved: true,
            id: document.id,
            name: document.name,
            size: document.size ?? 0,
            removed: form.remove_documents.includes(document.id),
        })),
        ...form.documents.map((file, index) => ({
            key: `new-${index}-${file.name}`,
            saved: false,
            index,
            name: file.name,
            size: file.size,
            preview: previews.get(file),
            // Los errores llegan por archivo: documents.0, documents.1…
            error: Boolean(form.errors[`documents.${index}`]),
        })),
    ].map((card) => ({ ...card, extension: extensionOf(card.name).toUpperCase(), kind: FILE_KINDS[kindOf(card.name)] ?? FILE_KINDS.pdf })),
);

function toggleCard(card) {
    if (!card.saved) return removeFile(card.index);

    form.remove_documents = card.removed ? form.remove_documents.filter((id) => id !== card.id) : [...form.remove_documents, card.id];
}

const previewOpen = ref(false);
const previewFile = ref(null);

/** El nuevo se ve desde memoria; el guardado se trae de su ruta de descarga. */
function openPreview(card) {
    previewFile.value = {
        name: card.name,
        size: card.size,
        source: card.saved ? `/polizas/${props.policy.id}/documentos/${card.id}` : form.documents[card.index],
    };
    previewOpen.value = true;
}

/* ---------- Envío ---------- */

function submit() {
    // Vacío viaja como null para que la base guarde «sin dato». Con endosos
    // el fin no se manda: lo mueve el último endoso.
    form.transform(({ valid_until, documents, remove_documents, ...data }) => ({
        ...Object.fromEntries(
            Object.entries(endorsed.value ? data : { ...data, valid_until }).map(([key, value]) => [key, value === '' ? null : value]),
        ),
        documents,
        remove_documents,
        // Con archivos la petición va como POST; el método real viaja aquí.
        ...(editing.value ? { _method: 'patch' } : {}),
    }));

    form.post(editing.value ? `/polizas/${props.policy.id}` : '/polizas', { preserveScroll: true, forceFormData: true });
}

const FIELD =
    'peer h-8 w-full rounded-lg border border-slate-200 bg-slate-50/70 pr-3 pl-9 text-[0.8rem] text-slate-900 outline-none ' +
    'transition-[border-color,background-color,box-shadow] duration-150 placeholder:text-slate-400 ' +
    'hover:border-slate-300 focus:border-brand/50 focus:bg-white focus:ring-4 focus:ring-brand/10 ' +
    'aria-invalid:border-red-400 aria-invalid:focus:ring-red-500/10 ' +
    'dark:border-white/10 dark:bg-white/[0.04] dark:text-white dark:placeholder:text-white/30 dark:hover:border-white/20 ' +
    'dark:focus:border-brand-gray/50 dark:focus:bg-white/[0.06] dark:focus:ring-white/10 dark:[color-scheme:dark] ' +
    'disabled:cursor-not-allowed disabled:opacity-60';

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
    <form id="policy-form" class="flex flex-col gap-2.5" novalidate @submit.prevent="submit">
        <!-- Póliza -->
        <section :class="CARD">
            <p :class="SECTION_TITLE">Póliza de obra</p>

            <div class="mt-2 grid gap-x-4 gap-y-2.5 sm:grid-cols-2 lg:grid-cols-3">
                <!-- Obra: por ahora a mano, mientras no haya catálogo de obras -->
                <div>
                    <label for="policy-project" :class="LABEL">
                        Obra
                        <span :class="REQUIRED">*</span>
                    </label>
                    <div class="relative">
                        <input
                            id="policy-project"
                            :value="form.project"
                            @input="toUpper('project', $event)"
                            type="text"
                            required
                            autocomplete="off"
                            placeholder="Nombre de la obra"
                            :class="FIELD"
                            :aria-invalid="Boolean(form.errors.project)"
                        />
                        <HardHat :class="ICON" />
                    </div>
                    <p v-if="form.errors.project" :class="ERROR">{{ form.errors.project }}</p>
                </div>

                <div>
                    <label for="policy-project-address" :class="LABEL">Dirección</label>
                    <div class="relative">
                        <input
                            id="policy-project-address"
                            :value="form.project_address"
                            @input="toUpper('project_address', $event)"
                            type="text"
                            autocomplete="off"
                            :class="FIELD"
                            :aria-invalid="Boolean(form.errors.project_address)"
                        />
                        <MapPin :class="ICON" />
                    </div>
                    <p v-if="form.errors.project_address" :class="ERROR">{{ form.errors.project_address }}</p>
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
                    <label for="policy-insurer" :class="LABEL">Aseguradora</label>
                    <FormSelect
                        compact
                        id="policy-insurer"
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
                    <label for="policy-coverage" :class="LABEL">
                        Cobertura
                        <span :class="REQUIRED">*</span>
                    </label>
                    <FormSelect
                        compact
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
                    <label for="policy-business" :class="LABEL">
                        Unidad de negocio
                        <span :class="REQUIRED">*</span>
                    </label>
                    <FormSelect
                        compact
                        id="policy-business"
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

        <!-- Vigencia y monto -->
        <section :class="CARD">
            <p :class="SECTION_TITLE">Vigencia y monto</p>

            <div class="mt-2 grid gap-x-4 gap-y-2.5 sm:grid-cols-2 lg:grid-cols-4">
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
                </div>

                <!-- El fin se captura a mano. Con endosos lo mueve el último. -->
                <div>
                    <label for="policy-valid-until" :class="LABEL">
                        Fin de vigencia
                        <span :class="REQUIRED">*</span>
                    </label>
                    <div class="relative">
                        <input
                            id="policy-valid-until"
                            v-model="form.valid_until"
                            type="date"
                            required
                            :min="form.valid_from || undefined"
                            :disabled="endorsed"
                            :class="FIELD"
                            :aria-invalid="Boolean(form.errors.valid_until)"
                        />
                        <CalendarCheck :class="ICON" />
                    </div>
                    <p v-if="form.errors.valid_until" :class="ERROR">{{ form.errors.valid_until }}</p>
                    <p v-else-if="endorsed" class="mt-1 text-[0.7rem] text-slate-400 dark:text-brand-gray/70">Lo marca el último endoso.</p>
                </div>

                <div>
                    <label for="policy-amount" :class="LABEL">Monto (IVA incluido)</label>
                    <div class="relative">
                        <input
                            id="policy-amount"
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

                <!-- El desglose del monto: ya trae el IVA, no se le suma -->
                <div class="flex flex-col justify-center rounded-xl bg-slate-50/70 px-3.5 py-1.5 ring-1 ring-inset ring-slate-200/70 dark:bg-white/[0.03] dark:ring-white/[0.06]">
                    <p class="text-[0.62rem] font-bold uppercase tracking-[0.14em] text-slate-400 dark:text-brand-gray/80">Total a pagar</p>
                    <p class="text-lg leading-tight font-bold tabular-nums text-brand dark:text-white">{{ money(total) }}</p>
                    <p class="text-[0.7rem] tabular-nums text-slate-500 dark:text-brand-gray">Subtotal: {{ money(total - tax) }} · IVA {{ Math.round(TAX_RATE * 100) }}%: {{ money(tax) }}</p>
                </div>
            </div>
        </section>

        <!-- Documentos y comentarios -->
        <section :class="CARD">
            <p :class="SECTION_TITLE">Documentos y comentarios</p>

            <div class="mt-2 grid gap-4 lg:grid-cols-2">
                <div>
                    <span :class="LABEL">Documentos</span>

                    <button
                        type="button"
                        class="flex h-24 w-full cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border border-dashed border-slate-300 bg-slate-50/70 px-4 text-center transition-colors duration-150 hover:border-brand/40 hover:bg-brand/[0.03] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/15 dark:border-white/15 dark:bg-white/[0.03] dark:hover:border-white/30 dark:hover:bg-white/[0.06]"
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
                        <p v-if="String(key).startsWith('documents')" :class="ERROR">{{ error }}</p>
                    </template>
                </div>

                <div>
                    <label for="policy-comments" :class="LABEL">Comentarios</label>
                    <div class="relative">
                        <textarea
                            id="policy-comments"
                            :value="form.comments"
                            @input="toUpper('comments', $event)"
                            rows="5"
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
                {{ editing ? 'Guardar cambios' : 'Guardar póliza' }}
            </button>
        </div>

        <!-- Vive en un portal: dentro del form solo para que el componente tenga una sola raíz -->
        <FilePreviewDialog v-model:open="previewOpen" :file="previewFile" />
    </form>
</template>
