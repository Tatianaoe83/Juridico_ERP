<script setup>
import { Check, ChevronDown } from 'lucide-vue-next';
import {
    SelectContent,
    SelectIcon,
    SelectItem,
    SelectItemIndicator,
    SelectItemText,
    SelectPortal,
    SelectRoot,
    SelectTrigger,
    SelectViewport,
} from 'reka-ui';
import { computed } from 'vue';

/**
 * Filtro de una tabla: siempre el mismo control, solo cambia por qué filtra.
 *
 * Lista propia en vez del <select> nativo porque este sí acepta un punto de
 * color por opción —lo que pide el estado— y se ve igual en los dos sistemas
 * operativos. Vacío significa «todos»; el resto lo maneja quien lo usa.
 */
const props = defineProps({
    /** '' = sin filtrar. */
    modelValue: { type: String, default: '' },
    /** [{ value, label, dot? }] — `dot` es una clase de color, opcional. */
    options: { type: Array, required: true },
    /** Lo que se lee cuando no hay nada elegido: «Todas las empresas». */
    placeholder: { type: String, required: true },
    /** Para lectores de pantalla: «Filtrar por empresa». */
    label: { type: String, required: true },
    /** Ancho en computadora; en móvil siempre ocupa todo. */
    width: { type: String, default: '@xl:w-48' },
    /** Icono de lucide a la izquierda: dice de qué es el filtro sin gastar texto. */
    icon: { type: [Object, Function], default: null },
});

const emit = defineEmits(['update:modelValue']);

/** El Select de reka-ui no acepta '' como valor: «todos» viaja como 'all'. */
const model = computed({
    get: () => props.modelValue || 'all',
    set: (value) => emit('update:modelValue', value === 'all' ? '' : value),
});

const selected = computed(() => props.options.find((option) => option.value === props.modelValue) ?? null);

// Si ninguna opción trae color, el punto sobra y el control se cierra más.
const hasDots = computed(() => props.options.some((option) => option.dot));
</script>

<template>
    <SelectRoot v-model="model">
        <SelectTrigger
            :aria-label="label"
            class="group flex h-9 w-full cursor-pointer items-center gap-2 rounded-lg border px-3 text-[0.8rem] outline-none transition-[border-color,background-color,box-shadow] duration-150 focus-visible:ring-4 focus-visible:ring-brand/10 data-[state=open]:border-brand/50 data-[state=open]:bg-white data-[state=open]:ring-4 data-[state=open]:ring-brand/10 dark:focus-visible:ring-white/10 dark:data-[state=open]:border-brand-gray/50 dark:data-[state=open]:bg-white/[0.06] dark:data-[state=open]:ring-white/10"
            :class="[
                width,
                selected
                    ? 'border-brand/50 bg-brand/[0.06] font-semibold text-brand shadow-sm shadow-brand/5 dark:border-brand-gray/50 dark:bg-white/[0.08] dark:text-white'
                    : 'border-transparent bg-slate-100/70 text-slate-500 hover:bg-slate-200/60 hover:text-slate-700 dark:bg-white/[0.04] dark:text-white/55 dark:hover:bg-white/[0.08] dark:hover:text-white',
            ]"
        >
            <!-- Punto de color si lo hay; si no, el icono del filtro -->
            <span v-if="hasDots" class="size-2 shrink-0 rounded-full" :class="selected?.dot ?? 'bg-slate-300 dark:bg-white/25'" />
            <component :is="icon" v-else-if="icon" class="size-3.5 shrink-0 opacity-70" />
            <span class="flex-1 truncate text-left">{{ selected?.label ?? placeholder }}</span>
            <SelectIcon as-child>
                <ChevronDown
                    class="size-3.5 shrink-0 text-slate-400 transition-transform duration-200 group-data-[state=open]:rotate-180 dark:text-white/35"
                />
            </SelectIcon>
        </SelectTrigger>

        <SelectPortal>
            <SelectContent
                position="popper"
                :side-offset="6"
                class="z-50 max-h-72 min-w-[var(--reka-select-trigger-width)] overflow-hidden rounded-xl border border-slate-200/80 bg-white p-1 font-corporate shadow-xl shadow-brand-deep/10 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:slide-in-from-top-1 dark:border-white/10 dark:bg-brand-panel dark:shadow-black/40"
            >
                <SelectViewport>
                    <SelectItem
                        value="all"
                        class="flex cursor-pointer items-center gap-2.5 rounded-lg py-2 pr-2 pl-2.5 text-[0.8rem] text-slate-700 outline-none select-none data-[highlighted]:bg-slate-100 data-[highlighted]:text-slate-900 data-[state=checked]:font-semibold data-[state=checked]:text-brand dark:text-slate-200 dark:data-[highlighted]:bg-white/[0.07] dark:data-[highlighted]:text-white dark:data-[state=checked]:text-white"
                    >
                        <span v-if="hasDots" class="size-2 shrink-0 rounded-full bg-slate-300 dark:bg-white/25" />
                        <SelectItemText class="flex-1">{{ placeholder }}</SelectItemText>
                        <SelectItemIndicator>
                            <Check class="size-3.5" stroke-width="2.5" />
                        </SelectItemIndicator>
                    </SelectItem>

                    <SelectItem
                        v-for="option in options"
                        :key="option.value"
                        :value="option.value"
                        class="flex cursor-pointer items-center gap-2.5 rounded-lg py-2 pr-2 pl-2.5 text-[0.8rem] text-slate-700 outline-none select-none data-[highlighted]:bg-slate-100 data-[highlighted]:text-slate-900 data-[state=checked]:font-semibold data-[state=checked]:text-brand dark:text-slate-200 dark:data-[highlighted]:bg-white/[0.07] dark:data-[highlighted]:text-white dark:data-[state=checked]:text-white"
                    >
                        <span v-if="hasDots" class="size-2 shrink-0 rounded-full" :class="option.dot ?? 'bg-slate-300 dark:bg-white/25'" />
                        <SelectItemText class="flex-1 truncate">{{ option.label }}</SelectItemText>
                        <SelectItemIndicator>
                            <Check class="size-3.5 shrink-0" stroke-width="2.5" />
                        </SelectItemIndicator>
                    </SelectItem>
                </SelectViewport>
            </SelectContent>
        </SelectPortal>
    </SelectRoot>
</template>
