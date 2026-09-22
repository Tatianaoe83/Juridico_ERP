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
 * Select de formulario: se ve como los inputs de texto de la app, no como el
 * <select> nativo, que cambia de un sistema operativo a otro y no admite un
 * punto de color por opción.
 *
 * Distinto de FilterSelect, que es para filtrar tablas: este marca el campo en
 * rojo cuando hay error y puede ofrecer «sin elegir» como una opción más.
 */
const props = defineProps({
    /** '' = sin elegir, solo válido cuando se pasa `placeholder`. */
    modelValue: { type: [String, Number], default: '' },
    /** [{ value, label, dot? }] — `dot` es una clase de color, opcional. */
    options: { type: Array, required: true },
    /** Texto de la opción vacía. Sin él, el campo obliga a elegir. */
    placeholder: { type: String, default: '' },
    /** Icono de lucide a la izquierda, igual que en los inputs. */
    icon: { type: [Object, Function], default: null },
    invalid: { type: Boolean, default: false },
    id: { type: String, default: undefined },
});

const emit = defineEmits(['update:modelValue']);

/** El Select de reka-ui no acepta '' como valor: la opción vacía viaja como '__none'. */
const NONE = '__none';

const model = computed({
    get: () => (props.modelValue === '' || props.modelValue === null ? NONE : String(props.modelValue)),
    set: (value) => emit('update:modelValue', value === NONE ? '' : value),
});

const selected = computed(() => props.options.find((option) => String(option.value) === String(props.modelValue)) ?? null);

// Si ninguna opción trae color, el punto sobra y el control se cierra más.
const hasDots = computed(() => props.options.some((option) => option.dot));

const ITEM =
    'flex cursor-pointer items-center gap-2.5 rounded-lg py-2 pr-2 pl-2.5 text-[0.8rem] text-slate-700 outline-none select-none ' +
    'data-[highlighted]:bg-slate-100 data-[highlighted]:text-slate-900 data-[state=checked]:font-semibold data-[state=checked]:text-brand ' +
    'dark:text-slate-200 dark:data-[highlighted]:bg-white/[0.07] dark:data-[highlighted]:text-white dark:data-[state=checked]:text-white';
</script>

<template>
    <SelectRoot v-model="model">
        <SelectTrigger
            :id="id"
            :aria-invalid="invalid"
            class="group flex h-9 w-full cursor-pointer items-center gap-2 rounded-lg border bg-slate-50/70 px-3 text-[0.8rem] outline-none transition-[border-color,background-color,box-shadow] duration-150 hover:border-slate-300 focus-visible:border-brand/50 focus-visible:bg-white focus-visible:ring-4 focus-visible:ring-brand/10 data-[state=open]:border-brand/50 data-[state=open]:bg-white data-[state=open]:ring-4 data-[state=open]:ring-brand/10 dark:bg-white/[0.04] dark:hover:border-white/20 dark:focus-visible:border-brand-gray/50 dark:focus-visible:bg-white/[0.06] dark:focus-visible:ring-white/10 dark:data-[state=open]:border-brand-gray/50 dark:data-[state=open]:bg-white/[0.06] dark:data-[state=open]:ring-white/10"
            :class="
                invalid
                    ? 'border-red-400 dark:border-red-400/60'
                    : 'border-slate-200 dark:border-white/10'
            "
        >
            <!-- Punto de color si lo hay; si no, el icono del campo -->
            <span
                v-if="hasDots"
                class="size-2 shrink-0 rounded-full"
                :class="selected?.dot ?? 'bg-slate-300 dark:bg-white/25'"
                aria-hidden="true"
            />
            <component :is="icon" v-else-if="icon" class="size-3.5 shrink-0 text-slate-400 transition-colors group-data-[state=open]:text-brand dark:text-white/35 dark:group-data-[state=open]:text-white" />

            <span class="flex-1 truncate text-left" :class="selected ? 'text-slate-900 dark:text-white' : 'text-slate-400 dark:text-white/30'">
                {{ selected?.label ?? placeholder }}
            </span>

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
                    <SelectItem v-if="placeholder" :value="NONE" :class="ITEM">
                        <span v-if="hasDots" class="size-2 shrink-0 rounded-full bg-slate-300 dark:bg-white/25" aria-hidden="true" />
                        <SelectItemText class="flex-1">{{ placeholder }}</SelectItemText>
                        <SelectItemIndicator>
                            <Check class="size-3.5 shrink-0" stroke-width="2.5" />
                        </SelectItemIndicator>
                    </SelectItem>

                    <SelectItem v-for="option in options" :key="option.value" :value="String(option.value)" :class="ITEM">
                        <span
                            v-if="hasDots"
                            class="size-2 shrink-0 rounded-full"
                            :class="option.dot ?? 'bg-slate-300 dark:bg-white/25'"
                            aria-hidden="true"
                        />
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
