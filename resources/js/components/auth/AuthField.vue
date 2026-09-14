<script setup>
import { Eye, EyeOff } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    type: { type: String, default: 'text' },
    modelValue: { type: [String, Number], default: '' },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    autocomplete: { type: String, default: '' },
    autofocus: { type: Boolean, default: false },
    required: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);

// Las contraseñas llevan botón para verlas: evita reescribirlas por un typo.
const isPassword = computed(() => props.type === 'password');
const revealed = ref(false);
const inputType = computed(() => (isPassword.value && revealed.value ? 'text' : props.type));

const describedBy = computed(
    () => [props.hint && `${props.id}-hint`, props.error && `${props.id}-error`].filter(Boolean).join(' ') || undefined,
);
</script>

<template>
    <div class="grid gap-2">
        <div class="flex items-baseline justify-between gap-4">
            <label :for="id" class="text-sm font-medium leading-none">{{ label }}</label>
            <span v-if="hint" :id="`${id}-hint`" class="text-xs text-muted-foreground">{{ hint }}</span>
        </div>

        <div class="relative">
            <input
                :id="id"
                :type="inputType"
                :value="modelValue"
                :placeholder="placeholder || undefined"
                :autocomplete="autocomplete || undefined"
                :autofocus="autofocus"
                :required="required"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="describedBy"
                class="h-11 w-full min-w-0 rounded-lg border border-input bg-transparent px-3 text-base shadow-xs outline-none transition-[border-color,box-shadow] placeholder:text-muted-foreground hover:border-ring/60 focus-visible:border-brand focus-visible:ring-[3px] focus-visible:ring-brand/20 dark:focus-visible:border-brand-gray dark:focus-visible:ring-brand-gray/25 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:bg-input/30 sm:h-10 sm:text-sm"
                :class="isPassword && 'pr-11'"
                @input="$emit('update:modelValue', $event.target.value)"
            />

            <button
                v-if="isPassword"
                type="button"
                class="absolute inset-y-0 right-0 grid w-11 cursor-pointer place-content-center rounded-r-lg text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                :aria-label="revealed ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                :aria-pressed="revealed"
                @click="revealed = !revealed"
            >
                <EyeOff v-if="revealed" class="size-4" aria-hidden="true" />
                <Eye v-else class="size-4" aria-hidden="true" />
            </button>
        </div>

        <p v-if="error" :id="`${id}-error`" role="alert" class="text-sm text-destructive">{{ error }}</p>
    </div>
</template>
