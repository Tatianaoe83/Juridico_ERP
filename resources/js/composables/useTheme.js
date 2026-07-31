import { ref, watch } from 'vue';

const STORAGE_KEY = 'theme';

/** Debe coincidir con la duración de .theme-transition en app.css. */
const FADE_MS = 200;

const isDark = ref(false);

function apply(dark) {
    document.documentElement.classList.toggle('dark', dark);
}

function prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/**
 * El cambio con fundido. Dos caminos según lo que soporte el navegador:
 *
 *  - View Transitions (Chrome, Edge): funde la página entera de una toma a la
 *    otra, iconos y sombras incluidos.
 *  - Resto: clase temporal que activa una transición de color en todo el árbol
 *    y se quita al terminar, para no dejarla pegada en cada hover.
 */
function applyWithFade(dark) {
    if (prefersReducedMotion()) {
        apply(dark);

        return;
    }

    if (typeof document.startViewTransition === 'function') {
        document.startViewTransition(() => apply(dark));

        return;
    }

    const root = document.documentElement;

    root.classList.add('theme-transition');
    apply(dark);

    window.setTimeout(() => root.classList.remove('theme-transition'), FADE_MS);
}

/**
 * La inicialización y el watch viven a nivel de módulo, no dentro de useTheme().
 *
 * Un watch creado dentro de setup() se destruye al desmontar el componente, y
 * con Inertia el layout se desmonta en cada navegación: el toggle seguía
 * cambiando isDark pero ya nadie aplicaba la clase al <html>.
 */
if (typeof window !== 'undefined') {
    const saved = localStorage.getItem(STORAGE_KEY);

    isDark.value = saved
        ? saved === 'dark'
        : window.matchMedia('(prefers-color-scheme: dark)').matches;

    // La primera aplicación va sin fundido: al cargar no hay nada desde donde
    // difuminar, y animarla se vería como un parpadeo.
    apply(isDark.value);

    watch(isDark, (dark) => {
        applyWithFade(dark);
        localStorage.setItem(STORAGE_KEY, dark ? 'dark' : 'light');
    });
}

/** Tema claro/oscuro persistido en localStorage. */
export function useTheme() {
    return {
        isDark,
        toggle: () => (isDark.value = !isDark.value),
    };
}
