<script setup>
defineProps({
    loading: { type: Boolean, default: false },
    loadingLabel: { type: String, default: 'Un momento…' },
});
</script>

<template>
    <button type="submit" class="submit" :disabled="loading">
        <span v-if="loading">{{ loadingLabel }}</span>
        <span v-else><slot /></span>
        <svg v-if="!loading" viewBox="0 0 16 16" aria-hidden="true">
            <path d="M1 8h13M9 3l5 5-5 5" fill="none" stroke="currentColor" stroke-width="1.5" />
        </svg>
    </button>
</template>

<style scoped>
.submit {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    width: 100%;
    padding: 0.85rem 1.1rem;
    border: 0;
    border-radius: 0;
    background: #14181c;
    color: #fff;
    font-size: 0.85rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    cursor: pointer;
    transition:
        background 150ms ease,
        color 150ms ease,
        transform 150ms ease;
}

.submit svg {
    width: 16px;
    height: 16px;
    transition: transform 150ms ease;
}

.submit:hover:not(:disabled) {
    background: #ffb320;
    color: #14181c;
}

.submit:hover:not(:disabled) svg {
    transform: translateX(3px);
}

.submit:focus-visible {
    outline: 2px solid #ffb320;
    outline-offset: 3px;
}

.submit:disabled {
    opacity: 0.6;
    cursor: progress;
}

@media (prefers-reduced-motion: reduce) {
    .submit,
    .submit svg {
        transition: none;
    }
}
</style>
