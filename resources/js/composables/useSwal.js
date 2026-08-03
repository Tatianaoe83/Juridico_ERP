import Swal from 'sweetalert2';

/**
 * SweetAlert2 vestido con los tokens de shadcn.
 *
 * `buttonsStyling: false` apaga los estilos propios de la librería para que los
 * botones se pinten con las mismas clases que el componente Button. Como el
 * tema vive en la clase .dark de <html> y el modal cuelga de <body>, las
 * variantes dark de Tailwind aplican solas.
 *
 * El `!` en todas las clases es obligatorio: SweetAlert2 inyecta su hoja en
 * runtime, o sea después de Tailwind, y con la misma especificidad gana la que
 * se declaró al final.
 */
const BUTTON =
    'inline-flex items-center justify-center !rounded-lg !border-0 !px-5 !py-2 !text-sm !font-medium ' +
    '!transition-all !duration-200 focus-visible:!outline-none focus-visible:!ring-[3px]';

/**
 * El botón de acción se eleva: sombra teñida de su propio color, que crece al
 * pasar el cursor. Da jerarquía sin necesidad de borde, que es lo que se veía
 * duro contra el fondo claro de la tarjeta.
 */
const LIFT = 'motion-safe:hover:!-translate-y-px active:!translate-y-0 active:!duration-75';

/**
 * customClass completo en cada variante. No se puede armar con `mixin()` encima
 * de otro mixin: SweetAlert2 fusiona los parámetros de forma superficial, así
 * que un customClass parcial borra el resto y el popup se queda sin estilos.
 */
function theme({ confirmButton, icon }) {
    return {
        buttonsStyling: false,
        reverseButtons: true,
        focusCancel: true,
        // Más oscuro y frío que el negro plano de la librería; el desenfoque
        // del contenedor hace el resto.
        backdrop: 'rgba(9, 11, 14, 0.55)',
        customClass: {
            container: '!backdrop-blur-[3px]',
            // Anillo en lugar de borde: se apoya en el color sin dibujar una
            // línea dura, y la sombra grande es la que separa del fondo.
            popup: '!rounded-2xl !border-0 !bg-card !text-foreground !shadow-2xl !ring-1 !ring-border/70',
            // Solo el color: el grosor del anillo lo define SweetAlert2 en em,
            // proporcional al tamaño del icono, y tocarlo lo descuadra.
            icon: icon,
            title: '!text-lg !font-semibold !text-foreground',
            htmlContainer: '!text-sm !text-muted-foreground',
            actions: '!mt-1 !gap-2.5',
            cancelButton:
                `${BUTTON} ${LIFT} !bg-transparent !text-foreground !ring-1 !ring-inset !ring-border ` +
                'hover:!bg-accent hover:!text-accent-foreground focus-visible:!ring-ring/40',
            confirmButton,
        },
    };
}

const base = Swal.mixin(
    theme({
        icon: '!border-primary/25 !text-primary',
        confirmButton:
            `${BUTTON} ${LIFT} !bg-primary !text-primary-foreground ` +
            '!shadow-lg !shadow-primary/25 hover:!bg-primary/90 hover:!shadow-xl hover:!shadow-primary/35 ' +
            'focus-visible:!ring-primary/40',
    }),
);

const destructive = Swal.mixin(
    theme({
        icon: '!border-destructive/25 !text-destructive',
        confirmButton:
            `${BUTTON} ${LIFT} !bg-destructive !text-white ` +
            '!shadow-lg !shadow-destructive/30 hover:!bg-destructive/90 hover:!shadow-xl hover:!shadow-destructive/40 ' +
            'focus-visible:!ring-destructive/40',
    }),
);

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
    async function confirm({ title, text = '', html = null, confirmText = 'Continuar' } = {}) {
        const { isConfirmed } = await base.fire({
            icon: 'question',
            title,
            ...(html ? { html } : { text }),
            showCancelButton: true,
            confirmButtonText: confirmText,
            cancelButtonText: 'Cancelar',
        });

        return isConfirmed;
    }

    return { confirmDelete, confirm };
}
