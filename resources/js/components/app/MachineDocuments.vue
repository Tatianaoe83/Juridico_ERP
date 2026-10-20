<script setup>
import { Plus, RotateCcw, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref } from 'vue';
import FilePreviewDialog from '@/components/app/FilePreviewDialog.vue';
import { ACCEPT, FILE_KINDS, extensionOf, fileSize, kindOf } from '@/lib/files';

/**
 * Los documentos de una máquina dentro del formulario de la póliza: los ya
 * guardados (se marcan para quitar, nada se borra hasta guardar) y los nuevos.
 * Tarjetas en fila, igual que en la unidad, para que muchas máquinas con
 * documentos no alarguen la página.
 */
const props = defineProps({
    /** Los guardados: [{ id, name, size }]. */
    saved: { type: Array, default: () => [] },
    /** La máquina, para ver y descargar los guardados; null si aún no existe. */
    unitId: { type: Number, default: null },
    /** Mensajes de error de sus archivos. */
    errors: { type: Array, default: () => [] },
});

/** Los nuevos (File[]). */
const files = defineModel('files', { type: Array, default: () => [] });

/** Ids de los guardados que se quitan al guardar. */
const removed = defineModel('removed', { type: Array, default: () => [] });

const input = ref(null);
const rejected = ref([]);
const duplicated = ref([]);

/** Miniatura de las imágenes nuevas; se liberan al quitarlas o al salir. */
const previews = new Map();

onBeforeUnmount(() => previews.forEach((url) => URL.revokeObjectURL(url)));

const fingerprint = (name, size) => `${name.toLowerCase()}|${size}`;

function add(event) {
    const taken = new Set([
        ...props.saved.filter((file) => !removed.value.includes(file.id)).map((file) => fingerprint(file.name, file.size ?? 0)),
        ...files.value.map((file) => fingerprint(file.name, file.size)),
    ]);

    rejected.value = [];
    duplicated.value = [];

    const accepted = [];

    for (const file of event.target.files) {
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

    accepted.filter((file) => kindOf(file.name) === 'image').forEach((file) => previews.set(file, URL.createObjectURL(file)));

    files.value = [...files.value, ...accepted];
    event.target.value = '';
}

const cards = computed(() =>
    [
        ...props.saved.map((file) => ({ key: `saved-${file.id}`, saved: true, id: file.id, name: file.name, size: file.size ?? 0, removed: removed.value.includes(file.id) })),
        ...files.value.map((file, index) => ({ key: `new-${index}-${file.name}`, saved: false, index, name: file.name, size: file.size, preview: previews.get(file) })),
    ].map((card) => ({ ...card, extension: extensionOf(card.name).toUpperCase(), kind: FILE_KINDS[kindOf(card.name)] ?? FILE_KINDS.pdf })),
);

function toggle(card) {
    if (!card.saved) {
        const file = files.value[card.index];

        if (previews.has(file)) {
            URL.revokeObjectURL(previews.get(file));
            previews.delete(file);
        }

        files.value = files.value.filter((_, i) => i !== card.index);
    } else if (card.removed) {
        removed.value = removed.value.filter((id) => id !== card.id);
    } else {
        removed.value = [...removed.value, card.id];
    }
}

/* ---------- Vista previa ---------- */

const previewOpen = ref(false);
const previewFile = ref(null);

/** El nuevo se ve desde memoria; el guardado se trae de su ruta de descarga. */
function openPreview(card) {
    previewFile.value = {
        name: card.name,
        size: card.size,
        source: card.saved ? `/flotillas/${props.unitId}/evidencias/${card.id}` : files.value[card.index],
    };
    previewOpen.value = true;
}

const ERROR = 'mt-1 text-[0.7rem] font-medium text-red-600 dark:text-red-400';
</script>

<template>
    <div>
        <!-- Una tarjeta por archivo, en fila; las que no caben bajan a la siguiente -->
        <ul class="flex flex-wrap gap-2">
            <li
                v-for="card in cards"
                :key="card.key"
                class="relative flex h-28 w-24 flex-col items-center gap-1.5 rounded-xl border px-2 pt-3 pb-2 text-center transition-opacity duration-150"
                :class="
                    card.removed
                        ? 'border-dashed border-red-300 bg-red-50/60 opacity-60 dark:border-red-500/30 dark:bg-red-500/[0.06]'
                        : 'border-slate-200 bg-white dark:border-white/10 dark:bg-white/[0.03]'
                "
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
                <span v-if="!card.saved" class="pointer-events-none absolute top-1.5 left-1.5 size-1.5 rounded-full bg-brand dark:bg-brand-gray" title="Por subir" />

                <img v-if="card.preview" :src="card.preview" alt="" class="size-10 shrink-0 rounded-lg object-cover ring-1 ring-slate-200 dark:ring-white/10" />
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
                    @click="toggle(card)"
                >
                    <RotateCcw v-if="card.removed" class="size-3.5" />
                    <X v-else class="size-3.5" />
                </button>
            </li>

            <!-- Agregar: una tarjeta más al final de la fila -->
            <li>
                <button
                    type="button"
                    class="flex h-28 w-24 cursor-pointer flex-col items-center justify-center gap-1.5 rounded-xl border border-dashed border-slate-300 px-2 text-center text-slate-500 transition-colors duration-150 hover:border-brand/40 hover:bg-brand/[0.03] hover:text-brand focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/10 dark:border-white/15 dark:text-brand-gray dark:hover:border-white/30 dark:hover:bg-white/[0.03] dark:hover:text-white"
                    @click="input?.click()"
                >
                    <Plus class="size-5" />
                    <span class="text-[0.66rem] leading-tight font-semibold">Agregar documentos</span>
                </button>
            </li>
        </ul>

        <input ref="input" type="file" multiple :accept="ACCEPT" class="hidden" @change="add" />

        <p v-if="rejected.length" :class="ERROR">No se agregó {{ rejected.join(', ') }}: solo PDF, Word, Excel, imágenes (JPG, PNG, WEBP) o XML.</p>
        <p v-if="duplicated.length" :class="ERROR">Ya está en la lista: {{ duplicated.join(', ') }}.</p>
        <p v-for="error in errors" :key="error" :class="ERROR">{{ error }}</p>

        <FilePreviewDialog v-model:open="previewOpen" :file="previewFile" />
    </div>
</template>
