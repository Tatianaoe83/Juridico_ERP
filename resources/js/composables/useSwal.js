import Swal from 'sweetalert2';

/**
 * SweetAlert2 vestido con los tokens de shadcn.
 *
 * `buttonsStyling: false` apaga los estilos propios de la librería para que los
 * botones se pinten con las mismas clases que el componente Button. Como el
 * tema vive en la clase .dark de <html> y el modal cuelga de <body>, las
 * variantes dark de Tailwind aplican solas.
 */
// El `!` es necesario: SweetAlert2 inyecta su hoja de estilos en runtime, o
// sea después de Tailwind, y con la misma especificidad gana la última.
const BUTTON =
    'inline-flex items-center justify-center !rounded-lg !border !border-black !px-4 !py-2 !text-sm !font-medium transition-colors';

/**
 * customClass completo. No se puede armar con `mixin()` encima de otro mixin:
 * SweetAlert2 fusiona los parámetros de forma superficial, así que un
 * customClass parcial borra el resto y el popup se queda sin estilos.
 */
function theme(confirmButton) {
    return {
        buttonsStyling: false,
        reverseButtons: true,
        focusCancel: true,
        customClass: {
            popup: '!rounded-xl !border !bg-card !text-foreground !shadow-lg',
            title: '!text-lg !font-semibold !text-foreground',
            htmlContainer: '!text-sm !text-muted-foreground',
            actions: '!gap-3',
            cancelButton: `${BUTTON} !bg-transparent hover:!bg-accent hover:!text-accent-foreground`,
            confirmButton,
        },
    };
}

const base = Swal.mixin(theme(`${BUTTON} !bg-primary !text-primary-foreground hover:!bg-primary/90`));

const destructive = Swal.mixin(theme(`${BUTTON} !bg-destructive !text-white hover:!bg-destructive/90`));

export function useSwal() {
    /**
     * Confirmación destructiva. Resuelve a true solo si el usuario acepta.
     *
     * @returns {Promise<boolean>}
     */
    async function confirmDelete({
        title = '¿Eliminar?',
        text = '',
        // Para mensajes con listas o énfasis. Si viene, sustituye a `text`.
        html = null,
        confirmText = 'Eliminar',
    } = {}) {
        const { isConfirmed } = await destructive.fire({
            icon: 'warning',
            title,
            ...(html ? { html } : { text }),
            showCancelButton: true,
            confirmButtonText: confirmText,
            cancelButtonText: 'Cancelar',
        });

        return isConfirmed;
    }

    /**
     * Confirmación normal, sin connotación destructiva.
     *
     * @returns {Promise<boolean>}
     */
    async function confirm({ title, text = '', confirmText = 'Continuar' } = {}) {
        const { isConfirmed } = await base.fire({
            icon: 'question',
            title,
            text,
            showCancelButton: true,
            confirmButtonText: confirmText,
            cancelButtonText: 'Cancelar',
        });

        return isConfirmed;
    }

    return { confirmDelete, confirm };
}
