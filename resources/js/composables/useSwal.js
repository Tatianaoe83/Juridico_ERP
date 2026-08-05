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
    const backdrop = document.documentElement.classList.contains('dark')
        ? 'rgba(16, 18, 24, 0.72)'
        : 'rgba(9, 11, 14, 0.55)';

    const popup =
        '!rounded-[28px] !border-0 !bg-[#111115] !text-foreground !shadow-2xl !ring-1 !ring-white/10';

    return {
        buttonsStyling: false,
        reverseButtons: true,
        focusCancel: true,
        backdrop,
        customClass: {
            backdrop: '!backdrop-blur-xl',
            // Anillo en lugar de borde: se apoya en el color sin dibujar una
            // línea dura, y la sombra grande es la que separa del fondo.
            popup,
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

/**
 * Piezas para armar el cuerpo de un modal.
 *
 * Existen para que los tres diálogos de la app compartan estructura: cuando
 * cada llamada escribía su propio markup, uno acababa con tarjetas, otro con
 * un párrafo suelto y otro con listas, y se notaba que eran de distinta mano.
 */
const blocks = {
    /** Frase de arriba: qué va a pasar, en una línea. */
    lead: (html) => `<p class="mb-3 text-center text-sm text-foreground">${html}</p>`,

    /** Valor literal — un rol, un permiso, un correo. */
    chip: (text) =>
        `<code class="rounded border bg-muted px-1.5 py-0.5 font-mono text-[0.7rem] text-foreground">${text}</code>`,

    /** Separador entre el estado anterior y el nuevo. */
    arrow: () => '<span class="mx-1.5 text-muted-foreground">→</span>',

    /**
     * Tarjeta con etiqueta y viñetas. `tone: 'danger'` para lo que se pierde,
     * y sin tono para lo que se conserva o para una nota informativa.
     */
    panel: ({ label, items, tone = 'muted' }) => {
        const danger = tone === 'danger';

        return `
            <div class="rounded-lg border ${danger ? 'border-destructive/30 bg-destructive/5' : 'bg-muted/30'} px-3.5 py-3 text-left">
                <p class="mb-1.5 text-[0.7rem] font-semibold uppercase tracking-wider ${danger ? 'text-destructive' : 'text-muted-foreground'}">
                    ${label}
                </p>
                <ul class="list-disc space-y-1 pl-4 text-sm text-foreground ${danger ? 'marker:text-destructive/50' : 'marker:text-muted-foreground/50'}">
                    ${items.map((item) => `<li>${item}</li>`).join('')}
                </ul>
            </div>
        `;
    },

    /** Aclaración final, más pequeña y centrada. */
    note: (html) => `<p class="mt-3.5 text-center text-xs text-muted-foreground">${html}</p>`,

    /** Une bloques descartando los vacíos, para poder condicionar alguno. */
    stack: (...parts) => `<div class="flex flex-col gap-2.5">${parts.filter(Boolean).join('')}</div>`,
};

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

    return { confirmDelete, confirm, blocks };
}
