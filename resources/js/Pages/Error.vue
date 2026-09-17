<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Home, Lock, ServerCrash, SearchX, Wrench } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps({
    status: { type: Number, required: true },
});

const page = usePage();

/** Sin sesión no hay a dónde «volver al inicio» salvo el login. */
const home = computed(() => (page.props.auth?.user ? '/calendario' : '/login'));

const CONTENT = {
    403: {
        icon: Lock,
        title: 'No tienes acceso',
        text: 'Esta sección está reservada. Si crees que deberías entrar, pídele a un administrador que revise tu rol.',
    },
    404: {
        icon: SearchX,
        title: 'Página no encontrada',
        text: 'La dirección no existe o el registro que buscabas ya se eliminó.',
    },
    500: {
        icon: ServerCrash,
        title: 'Algo salió mal',
        text: 'Hubo un error en el servidor. Vuelve a intentarlo en un momento.',
    },
    503: {
        icon: Wrench,
        title: 'En mantenimiento',
        text: 'El sistema está en mantenimiento. Estará de vuelta en unos minutos.',
    },
};

const info = computed(() => CONTENT[props.status] ?? CONTENT[500]);

/** El botón de volver solo sirve si se llegó desde otra página de la app. */
function back() {
    if (window.history.length > 1) {
        window.history.back();
        return;
    }

    window.location.href = home.value;
}
</script>

<template>
    <Head :title="`${status} · ${info.title}`" />

    <div class="grid min-h-svh place-items-center bg-slate-50 px-4 py-10 dark:bg-brand-deep">
        <div
            class="w-full max-w-md rounded-2xl border border-slate-200/80 bg-white px-6 py-10 text-center shadow-[0_1px_3px_rgb(2_29_73/0.04),0_8px_24px_-12px_rgb(2_29_73/0.08)] sm:px-10 dark:border-white/[0.08] dark:bg-brand-panel dark:shadow-none"
        >
            <span
                class="mx-auto grid size-12 place-content-center rounded-2xl bg-gradient-to-br from-brand-light to-brand text-white shadow-md shadow-brand/25 ring-1 ring-white/10"
            >
                <component :is="info.icon" class="size-6" />
            </span>

            <p class="mt-6 text-[0.6rem] font-bold uppercase tracking-[0.2em] text-slate-400 dark:text-brand-gray/80">Error {{ status }}</p>

            <h1 class="mt-1 text-2xl font-bold tracking-tight text-brand dark:text-white">{{ info.title }}</h1>

            <p class="mt-3 text-[0.85rem] leading-relaxed text-slate-500 dark:text-brand-gray">{{ info.text }}</p>

            <div class="mt-8 flex flex-col-reverse gap-2 sm:flex-row sm:justify-center">
                <button
                    type="button"
                    class="inline-flex h-9 cursor-pointer items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 text-[0.8rem] font-semibold text-slate-600 transition-colors duration-150 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/25 dark:border-white/10 dark:text-brand-gray dark:hover:bg-white/[0.06] dark:hover:text-white"
                    @click="back"
                >
                    <ArrowLeft class="size-4" />
                    Volver
                </button>

                <Link
                    :href="home"
                    class="inline-flex h-9 items-center justify-center gap-2 rounded-xl bg-brand px-4 text-[0.8rem] font-semibold text-white shadow-md shadow-brand/25 transition-all duration-150 hover:bg-brand/90 hover:shadow-lg hover:shadow-brand/30 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/25 active:translate-y-px dark:bg-brand-light dark:shadow-black/30 dark:hover:bg-brand-light/90"
                >
                    <Home class="size-4" />
                    Ir al inicio
                </Link>
            </div>
        </div>
    </div>
</template>
