<script setup>
import { router } from '@inertiajs/vue3';
import { BellRing } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps({
    /** PATCH que prende o apaga los recordatorios. */
    url: { type: String, required: true },
    enabled: { type: Boolean, default: false },
    /** De qué son, para el lector de pantalla: «la póliza 123». */
    label: { type: String, required: true },
});

const toggling = ref(false);

function toggle() {
    toggling.value = true;

    router.patch(props.url, {}, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => (toggling.value = false),
    });
}
</script>

<template>
    <!-- Recordatorios recurrentes por correo: en color y con punto cuando están prendidos. Mismo botón que en licencias. -->
    <button
        type="button"
        :class="[
            'relative grid size-7 cursor-pointer place-content-center rounded-lg transition-colors duration-150 focus-visible:outline-none focus-visible:ring-[3px] disabled:pointer-events-none disabled:opacity-40',
            enabled
                ? 'bg-amber-50 text-amber-600 hover:bg-amber-100 focus-visible:ring-amber-500/25 dark:bg-amber-400/10 dark:text-amber-300 dark:hover:bg-amber-400/20'
                : 'text-slate-500 hover:bg-brand/[0.07] hover:text-brand focus-visible:ring-brand/25 dark:text-brand-gray dark:hover:bg-white/10 dark:hover:text-white',
        ]"
        :aria-label="`${enabled ? 'Desactivar' : 'Activar'} recordatorios por correo de ${label}`"
        :aria-pressed="enabled"
        :title="enabled ? 'Recordatorios por correo activos' : 'Activar recordatorios por correo'"
        :disabled="toggling"
        @click="toggle"
    >
        <BellRing class="size-4" />
        <span v-if="enabled" class="absolute top-1 right-1 size-1.5 rounded-full bg-amber-500 ring-2 ring-white dark:ring-brand-deep" aria-hidden="true" />
    </button>
</template>
