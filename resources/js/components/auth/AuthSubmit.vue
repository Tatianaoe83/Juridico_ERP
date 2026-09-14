<script setup>
import { Loader2 } from 'lucide-vue-next';

defineProps({
    loading: { type: Boolean, default: false },
    loadingLabel: { type: String, default: 'Un momento…' },
});
</script>

<template>
    <button
        type="submit"
        :disabled="loading"
        :aria-busy="loading"
        class="inline-flex h-11 w-full cursor-pointer items-center justify-center gap-2 rounded-lg bg-brand px-4 text-sm font-bold text-white shadow-lg shadow-brand/25 transition-[background-color,box-shadow,transform] duration-150 hover:bg-brand-light hover:shadow-brand/35 focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-brand/40 dark:bg-brand-light dark:hover:bg-[#3a5f9e] dark:focus-visible:ring-brand-gray/50 active:scale-[0.99] disabled:cursor-progress disabled:opacity-70 motion-reduce:transition-none sm:h-10"
    >
        <Loader2 v-if="loading" class="size-4 animate-spin" aria-hidden="true" />
        <span v-if="loading">{{ loadingLabel }}</span>
        <slot v-else />
    </button>
</template>
