import { FileCode, FileImage, FileSpreadsheet, FileText } from 'lucide-vue-next';

/**
 * Los archivos que se aceptan como documento, los mismos que valida el
 * servidor (UnitEvidence::DOCUMENT_TYPES). Cada tipo trae su icono y su color
 * para reconocerlo de un vistazo.
 */
export const FILE_KINDS = {
    pdf: { label: 'PDF', icon: FileText, tile: 'bg-red-50 text-red-600 dark:bg-red-500/15 dark:text-red-300' },
    word: { label: 'Word', icon: FileText, tile: 'bg-blue-50 text-blue-600 dark:bg-blue-500/15 dark:text-blue-300' },
    excel: { label: 'Excel', icon: FileSpreadsheet, tile: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300' },
    image: { label: 'Imagen', icon: FileImage, tile: 'bg-violet-50 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300' },
    xml: { label: 'XML', icon: FileCode, tile: 'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300' },
};

export const EXTENSIONS = {
    pdf: 'pdf',
    doc: 'word',
    docx: 'word',
    xls: 'excel',
    xlsx: 'excel',
    jpg: 'image',
    jpeg: 'image',
    png: 'image',
    webp: 'image',
    xml: 'xml',
};

/** Para el `accept` del input: «.pdf,.doc,…». */
export const ACCEPT = Object.keys(EXTENSIONS).map((extension) => `.${extension}`).join(',');

/** «factura.PDF» → «pdf»; sin punto, cadena vacía. */
export function extensionOf(name) {
    const dot = name.lastIndexOf('.');

    return dot === -1 ? '' : name.slice(dot + 1).toLowerCase();
}

/** «pdf», «word»… o undefined si no es un tipo aceptado. */
export function kindOf(name) {
    return EXTENSIONS[extensionOf(name)];
}

/** «1.4 MB», «812 KB»: el peso importa porque el límite es de 10 MB por archivo. */
export function fileSize(bytes) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}
