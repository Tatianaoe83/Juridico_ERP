<script setup>
defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    type: { type: String, default: 'text' },
    modelValue: { type: [String, Number], default: '' },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    autocomplete: { type: String, default: '' },
    autofocus: { type: Boolean, default: false },
    required: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="field" :class="{ 'field-error': error }">
        <label :for="id">
            {{ label }}
            <span v-if="hint" class="hint">{{ hint }}</span>
        </label>

        <input
            :id="id"
            :type="type"
            :value="modelValue"
            :autocomplete="autocomplete || undefined"
            :autofocus="autofocus"
            :required="required"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="error ? `${id}-error` : undefined"
            @input="$emit('update:modelValue', $event.target.value)"
        />

        <p v-if="error" :id="`${id}-error`" class="error">{{ error }}</p>
    </div>
</template>

<style scoped>
.field {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

label {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 1rem;
    font-family: ui-monospace, 'Cascadia Mono', 'Segoe UI Mono', monospace;
    font-size: 0.62rem;
    letter-spacing: 0.24em;
    text-transform: uppercase;
    color: #5d6b76;
}

.hint {
    letter-spacing: 0.1em;
    text-transform: none;
    color: #8d9ba6;
}

input {
    width: 100%;
    border: 0;
    border-bottom: 1px solid #c8c6c0;
    border-radius: 0;
    background: transparent;
    padding: 0.5rem 0.1rem;
    font-size: 0.98rem;
    color: #14181c;
    outline: none;
    transition:
        border-color 140ms ease,
        box-shadow 140ms ease;
}

input:hover {
    border-bottom-color: #8d9ba6;
}

input:focus {
    border-bottom-color: #ffb320;
    box-shadow: 0 1px 0 0 #ffb320;
}

.field-error input {
    border-bottom-color: #c0392b;
}

.error {
    font-size: 0.78rem;
    color: #a5311f;
}
</style>
