<script setup>
import { AlertTriangle, Download, Loader2 } from 'lucide-vue-next';
import { DialogClose } from 'reka-ui';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AppModal from '@/components/app/AppModal.vue';
import { FILE_KINDS, extensionOf, fileSize, kindOf } from '@/lib/files';

/**
 * Vista previa de un documento, recién elegido o ya guardado. Imagen y PDF los
 * muestra el navegador; el XML se acomoda con sangría; Word y Excel se
 * dibujan aquí con sus librerías, que se cargan solo cuando hacen falta.
 */
const props = defineProps({
    open: { type: Boolean, default: false },
    /** { name, size, source }: `source` es el File elegido o la URL del guardado. */
    file: { type: Object, default: null },
});

const emit = defineEmits(['update:open']);

/** Filas y columnas que se dibujan por hoja: suficiente para revisar sin trabar la página. */
const MAX_ROWS = 500;
const MAX_COLUMNS = 50;

const status = ref('idle'); // idle · loading · ready · unsupported · error
const objectUrl = ref(null);
const xmlText = ref('');
const sheets = ref([]);
const activeSheet = ref(0);
const docxContainer = ref(null);

const kind = computed(() => (props.file ? kindOf(props.file.name) : null));
const meta = computed(() => FILE_KINDS[kind.value] ?? FILE_KINDS.pdf);
const extension = computed(() => (props.file ? extensionOf(props.file.name) : ''));

/** Para descargar: el guardado baja de su ruta; el nuevo, del blob en memoria. */
const downloadUrl = computed(() => (typeof props.file?.source === 'string' ? props.file.source : objectUrl.value));

// Cada carga lleva su número: si cambian de archivo a media carga, la vieja se descarta.
let ticket = 0;

function reset() {
    ticket++;

    if (objectUrl.value) URL.revokeObjectURL(objectUrl.value);

    objectUrl.value = null;
    xmlText.value = '';
    sheets.value = [];
    activeSheet.value = 0;
    status.value = 'idle';

    if (docxContainer.value) docxContainer.value.innerHTML = '';
}

async function blobOf(source) {
    if (source instanceof Blob) return source;

    const response = await fetch(source, { credentials: 'same-origin' });

    if (!response.ok) throw new Error(`HTTP ${response.status}`);

    return response.blob();
}

async function load() {
    reset();

    const current = ticket;
    const file = props.file;

    // Word 97-2003 es binario y el navegador no tiene con qué leerlo.
    if (extension.value === 'doc') {
        status.value = 'unsupported';
        if (file.source instanceof Blob) objectUrl.value = URL.createObjectURL(file.source);
        return;
    }

    status.value = 'loading';

    try {
        const blob = await blobOf(file.source);
        if (current !== ticket) return;

        if (kind.value === 'pdf') {
            // El tipo explícito hace que el visor del navegador lo abra en lugar de descargarlo.
            objectUrl.value = URL.createObjectURL(new Blob([blob], { type: 'application/pdf' }));
        } else if (kind.value === 'image') {
            objectUrl.value = URL.createObjectURL(blob);
        } else if (kind.value === 'xml') {
            xmlText.value = formatXml(await blob.text());
            objectUrl.value = URL.createObjectURL(blob);
        } else if (kind.value === 'excel') {
            sheets.value = await readSheets(blob);
            objectUrl.value = URL.createObjectURL(blob);
        } else if (kind.value === 'word') {
            // El contenedor ya está en pantalla (se pinta para Word desde que empieza a cargar).
            objectUrl.value = URL.createObjectURL(blob);
            await nextTick();

            const { renderAsync } = await import('docx-preview');
            if (current !== ticket) return;

            await renderAsync(blob, docxContainer.value, null, { inWrapper: true, ignoreLastRenderedPageBreak: true });
        }

        if (current === ticket) status.value = 'ready';
    } catch {
        if (current === ticket) status.value = 'error';
    }
}

async function readSheets(blob) {
    const XLSX = await import('xlsx');
    const book = XLSX.read(await blob.arrayBuffer(), { type: 'array', cellDates: true });

    return book.SheetNames.map((name) => {
        const rows = XLSX.utils.sheet_to_json(book.Sheets[name], { header: 1, raw: false, defval: '' });
        const width = Math.min(MAX_COLUMNS, Math.max(0, ...rows.map((row) => row.length)));

        return {
            name,
            columns: Array.from({ length: width }, (_, index) => XLSX.utils.encode_col(index)),
            rows: rows.slice(0, MAX_ROWS).map((row) => Array.from({ length: width }, (_, index) => row[index] ?? '')),
            truncated: rows.length > MAX_ROWS || rows.some((row) => row.length > MAX_COLUMNS),
        };
    });
}

/**
 * El XML con una etiqueta por renglón y su sangría. Si viene con más de tres
 * atributos (un CFDI trae decenas) van uno por renglón para poder leerlos.
 * Si no se puede leer, se muestra tal cual.
 */
function formatXml(text) {
    const document = new DOMParser().parseFromString(text, 'application/xml');

    if (document.getElementsByTagName('parsererror').length) return text;

    const declaration = text.trimStart().match(/^<\?xml[^?]*\?>/)?.[0];

    function serialize(node, depth) {
        const pad = '  '.repeat(depth);

        if (node.nodeType === Node.TEXT_NODE) return pad + node.nodeValue.trim();
        if (node.nodeType === Node.CDATA_SECTION_NODE) return `${pad}<![CDATA[${node.nodeValue}]]>`;
        if (node.nodeType === Node.COMMENT_NODE) return `${pad}<!--${node.nodeValue}-->`;
        if (node.nodeType !== Node.ELEMENT_NODE) return '';

        const attributes = Array.from(node.attributes).map(({ name, value }) => `${name}="${value}"`);
        const open =
            attributes.length > 3
                ? `${pad}<${node.tagName}\n${attributes.map((attribute) => `${pad}    ${attribute}`).join('\n')}`
                : `${pad}<${[node.tagName, ...attributes].join(' ')}`;

        const children = Array.from(node.childNodes).filter((child) => !(child.nodeType === Node.TEXT_NODE && !child.nodeValue.trim()));

        if (!children.length) return `${open} />`;

        if (children.length === 1 && children[0].nodeType === Node.TEXT_NODE) {
            return `${open}>${children[0].nodeValue.trim()}</${node.tagName}>`;
        }

        return `${open}>\n${children.map((child) => serialize(child, depth + 1)).join('\n')}\n${pad}</${node.tagName}>`;
    }

    const body = Array.from(document.childNodes).map((node) => serialize(node, 0)).filter(Boolean).join('\n');

    return declaration ? `${declaration}\n${body}` : body;
}

watch(
    () => [props.open, props.file],
    ([open]) => (open && props.file ? load() : reset()),
);

onBeforeUnmount(reset);

const BUTTON =
    'inline-flex h-9 cursor-pointer items-center justify-center gap-2 rounded-xl px-4 text-[0.8rem] font-semibold transition-colors duration-150 focus-visible:outline-none focus-visible:ring-4';
</script>

<template>
    <AppModal
        :open="open"
        size="xl"
        :icon="meta.icon"
        :title="file?.name ?? ''"
        :description="file ? `${meta.label} · ${fileSize(file.size ?? 0)}` : ''"
        @update:open="emit('update:open', $event)"
    >
        <div class="px-6 pb-6 sm:px-7">
            <div class="relative h-[65dvh] overflow-hidden rounded-xl border border-slate-200 bg-slate-50 dark:border-white/10 dark:bg-white/[0.03]">
                <!-- Cargando -->
                <div v-if="status === 'loading'" class="absolute inset-0 z-10 grid place-content-center bg-slate-50/80 dark:bg-brand-panel/80">
                    <Loader2 class="size-6 animate-spin text-slate-400 dark:text-brand-gray" />
                </div>

                <!-- Sin vista previa o con error: se ofrece descargarlo -->
                <div
                    v-if="status === 'unsupported' || status === 'error'"
                    class="flex h-full flex-col items-center justify-center gap-2 px-6 text-center"
                >
                    <span class="grid size-12 place-content-center rounded-xl" :class="status === 'error' ? 'bg-red-50 text-red-600 dark:bg-red-500/15 dark:text-red-300' : meta.tile">
                        <AlertTriangle v-if="status === 'error'" class="size-6" />
                        <component :is="meta.icon" v-else class="size-6" />
                    </span>
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                        {{ status === 'error' ? 'No se pudo abrir la vista previa' : 'Este formato no tiene vista previa' }}
                    </p>
                    <p class="max-w-sm text-xs text-slate-500 dark:text-brand-gray">
                        {{
                            status === 'error'
                                ? 'El archivo puede estar dañado o protegido. Descárgalo para abrirlo en su programa.'
                                : 'Los documentos de Word 97-2003 (.doc) no se pueden mostrar en el navegador. Descárgalo para abrirlo en Word.'
                        }}
                    </p>
                </div>

                <template v-else-if="status === 'ready' || kind === 'word'">
                    <img v-if="kind === 'image' && objectUrl" :src="objectUrl" :alt="file?.name" class="size-full object-contain p-3" />

                    <iframe v-else-if="kind === 'pdf' && objectUrl" :src="objectUrl" :title="file?.name" class="size-full bg-white" />

                    <pre
                        v-else-if="kind === 'xml'"
                        class="size-full overflow-auto p-4 font-mono text-[0.72rem] leading-relaxed whitespace-pre text-slate-700 dark:text-slate-200"
                    >{{ xmlText }}</pre>

                    <!-- Excel: una pestaña por hoja -->
                    <div v-else-if="kind === 'excel'" class="flex size-full flex-col">
                        <div v-if="sheets.length > 1" class="flex shrink-0 gap-1 overflow-x-auto border-b border-slate-200 bg-white px-2 pt-2 dark:border-white/10 dark:bg-transparent">
                            <button
                                v-for="(sheet, index) in sheets"
                                :key="sheet.name"
                                type="button"
                                class="shrink-0 cursor-pointer rounded-t-lg px-3 py-1.5 text-[0.72rem] font-semibold transition-colors"
                                :class="
                                    index === activeSheet
                                        ? 'bg-slate-50 text-brand ring-1 ring-slate-200 dark:bg-white/[0.06] dark:text-white dark:ring-white/10'
                                        : 'text-slate-500 hover:text-slate-800 dark:text-brand-gray dark:hover:text-white'
                                "
                                @click="activeSheet = index"
                            >
                                {{ sheet.name }}
                            </button>
                        </div>

                        <div v-if="sheets[activeSheet]" class="min-h-0 flex-1 overflow-auto">
                            <table class="border-collapse text-[0.72rem] text-slate-700 dark:text-slate-200">
                                <thead class="sticky top-0 z-[1]">
                                    <tr>
                                        <th class="sticky left-0 z-[2] min-w-10 border border-slate-200 bg-slate-100 px-2 py-1 dark:border-white/10 dark:bg-brand-deep" />
                                        <th
                                            v-for="column in sheets[activeSheet].columns"
                                            :key="column"
                                            class="min-w-20 border border-slate-200 bg-slate-100 px-2 py-1 font-semibold text-slate-500 dark:border-white/10 dark:bg-brand-deep dark:text-brand-gray"
                                        >
                                            {{ column }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(row, rowIndex) in sheets[activeSheet].rows" :key="rowIndex">
                                        <th
                                            class="sticky left-0 border border-slate-200 bg-slate-100 px-2 py-1 text-right font-semibold tabular-nums text-slate-500 dark:border-white/10 dark:bg-brand-deep dark:text-brand-gray"
                                        >
                                            {{ rowIndex + 1 }}
                                        </th>
                                        <td
                                            v-for="(cell, cellIndex) in row"
                                            :key="cellIndex"
                                            class="max-w-64 truncate border border-slate-200 bg-white px-2 py-1 dark:border-white/10 dark:bg-transparent"
                                            :title="String(cell)"
                                        >
                                            {{ cell }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <p v-if="!sheets[activeSheet].rows.length" class="p-4 text-xs text-slate-500 dark:text-brand-gray">La hoja está vacía.</p>
                        </div>

                        <p
                            v-if="sheets[activeSheet]?.truncated"
                            class="shrink-0 border-t border-slate-200 bg-white px-3 py-1.5 text-[0.68rem] text-slate-500 dark:border-white/10 dark:bg-transparent dark:text-brand-gray"
                        >
                            Se muestran las primeras {{ MAX_ROWS }} filas y {{ MAX_COLUMNS }} columnas. Descárgalo para verlo completo.
                        </p>
                    </div>

                    <!-- Word: docx-preview dibuja las páginas aquí dentro -->
                    <div v-else-if="kind === 'word'" ref="docxContainer" class="size-full overflow-auto" />
                </template>
            </div>
        </div>

        <template #footer>
            <DialogClose
                :class="[
                    BUTTON,
                    'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:text-slate-900 focus-visible:ring-slate-500/10 dark:border-white/10 dark:bg-transparent dark:text-brand-gray dark:hover:bg-white/[0.06] dark:hover:text-white',
                ]"
            >
                Cerrar
            </DialogClose>
            <a
                v-if="downloadUrl"
                :href="downloadUrl"
                :download="file?.name"
                :class="[
                    BUTTON,
                    'bg-brand text-white shadow-md shadow-brand/25 hover:bg-brand/90 focus-visible:ring-brand/25 dark:bg-brand-light dark:hover:bg-brand-light/90',
                ]"
            >
                <Download class="size-4" />
                Descargar
            </a>
        </template>
    </AppModal>
</template>
