<script setup>
defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    modelValue: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <label class="check" :for="id">
        <input
            :id="id"
            type="checkbox"
            :checked="modelValue"
            @change="$emit('update:modelValue', $event.target.checked)"
        />
        <span>{{ label }}</span>
    </label>
</template>

<style scoped>
.check {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    font-size: 0.85rem;
    color: #4b5661;
    cursor: pointer;
}

input {
    appearance: none;
    width: 1rem;
    height: 1rem;
    border: 1px solid #b4b2ac;
    border-radius: 0;
    background: transparent;
    display: grid;
    place-content: center;
    cursor: pointer;
    transition: border-color 140ms ease;
}

input:hover {
    border-color: #8d9ba6;
}

input::after {
    content: '';
    width: 0.5rem;
    height: 0.5rem;
    background: #ffb320;
    transform: scale(0);
    transition: transform 120ms ease;
}

input:checked {
    border-color: #14181c;
}

input:checked::after {
    transform: scale(1);
}

input:focus-visible {
    outline: 2px solid #ffb320;
    outline-offset: 2px;
}
</style>
