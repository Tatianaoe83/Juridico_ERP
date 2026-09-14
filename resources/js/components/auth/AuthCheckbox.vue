<script setup>
import { Check } from 'lucide-vue-next';

defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    modelValue: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <label :for="id" class="inline-flex w-fit cursor-pointer select-none items-center gap-2.5 py-1 text-sm">
        <span class="relative grid size-4 shrink-0 place-content-center">
            <!-- bg-none quita la palomita que pinta @tailwindcss/forms: en
                 tema oscuro quedaba blanca sobre fondo claro. -->
            <input
                :id="id"
                type="checkbox"
                :checked="modelValue"
                class="peer size-4 cursor-pointer appearance-none rounded-[4px] border border-input bg-transparent shadow-xs transition-colors checked:border-brand checked:bg-brand checked:bg-none focus:ring-0 focus:ring-offset-0 focus-visible:ring-[3px] focus-visible:ring-brand/30 dark:bg-input/30 dark:checked:border-brand-light dark:checked:bg-brand-light"
                @change="$emit('update:modelValue', $event.target.checked)"
            />
            <Check
                class="pointer-events-none absolute size-3.5 text-white opacity-0 transition-opacity peer-checked:opacity-100"
                stroke-width="3"
                aria-hidden="true"
            />
        </span>
        <span class="text-muted-foreground">{{ label }}</span>
    </label>
</template>
