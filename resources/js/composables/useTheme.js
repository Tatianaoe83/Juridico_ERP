import { ref, watch } from 'vue';

const STORAGE_KEY = 'theme';
const isDark = ref(false);

function apply(dark) {
    document.documentElement.classList.toggle('dark', dark);
}

/** Tema claro/oscuro persistido en localStorage. */
export function useTheme() {
    if (typeof window !== 'undefined' && !window.__themeReady) {
        const saved = localStorage.getItem(STORAGE_KEY);
        isDark.value = saved
            ? saved === 'dark'
            : window.matchMedia('(prefers-color-scheme: dark)').matches;
        apply(isDark.value);
        window.__themeReady = true;

        watch(isDark, (dark) => {
            apply(dark);
            localStorage.setItem(STORAGE_KEY, dark ? 'dark' : 'light');
        });
    }

    return {
        isDark,
        toggle: () => (isDark.value = !isDark.value),
    };
}
