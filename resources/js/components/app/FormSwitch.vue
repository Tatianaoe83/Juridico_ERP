<script setup>
/**
 * Interruptor de sí/no para los formularios, con su etiqueta arriba como los
 * demás campos: la perilla se desliza y el color cambia al mismo ritmo.
 */
defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    /** Lo que dice prendido y apagado. */
    on: { type: String, required: true },
    off: { type: String, required: true },
    disabled: { type: Boolean, default: false },
    error: { type: String, default: null },
    /** Debajo, cuando no hay error. */
    hint: { type: String, default: null },
});

const model = defineModel({ type: Boolean, default: false });

const LABEL = 'mb-1.5 block text-[0.75rem] font-semibold text-slate-700 dark:text-slate-200';
</script>

<template>
    <div>
        <span :id="`${id}-label`" :class="LABEL">{{ label }}</span>
        <button
            :id="id"
            type="button"
            role="switch"
            :aria-checked="model"
            :aria-labelledby="`${id}-label`"
            :disabled="disabled"
            class="group flex h-9 w-full cursor-pointer items-center gap-3 rounded-lg border px-3 text-left transition-colors duration-300 ease-in-out focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/15 disabled:cursor-not-allowed disabled:opacity-60"
            :class="
                model
                    ? 'border-brand/30 bg-brand/[0.05] dark:border-white/25 dark:bg-white/[0.07]'
                    : 'border-slate-200 bg-slate-50/70 hover:border-slate-300 dark:border-white/10 dark:bg-white/[0.04] dark:hover:border-white/20'
            "
            @click="model = !model"
        >
            <span
                class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors duration-300 ease-in-out"
                :class="model ? 'bg-brand dark:bg-brand-light' : 'bg-slate-300 dark:bg-white/20'"
            >
                <span
                    class="absolute left-0.5 size-4 rounded-full bg-white shadow-sm transition-transform duration-300 ease-in-out"
                    :class="model ? 'translate-x-4' : 'translate-x-0'"
                />
            </span>
            <span
                class="text-[0.8rem] font-semibold transition-colors duration-300 ease-in-out"
                :class="model ? 'text-brand dark:text-white' : 'text-slate-500 dark:text-brand-gray'"
            >
                {{ model ? on : off }}
            </span>
        </button>
        <p v-if="error" class="mt-1 text-[0.7rem] font-medium text-red-600 dark:text-red-400">{{ error }}</p>
        <p v-else-if="hint" class="mt-1 text-[0.7rem] text-slate-400 dark:text-brand-gray/70">{{ hint }}</p>
    </div>
</template>
