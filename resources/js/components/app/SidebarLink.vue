<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    href: { type: String, required: true },
    label: { type: String, required: true },
    icon: { type: [Object, Function], required: true },
});

const page = usePage();
const active = computed(() => page.url.split('?')[0].startsWith(props.href));
</script>

<template>
    <Link
        :href="href"
        :aria-current="active ? 'page' : undefined"
        class="group relative flex h-10 items-center gap-3 rounded-lg px-3 text-sm transition-colors duration-150 focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-brand/30 dark:focus-visible:ring-white/30"
        :class="
            active
                ? 'bg-brand/[0.07] font-semibold text-brand dark:bg-white/[0.08] dark:text-white'
                : 'text-slate-600 hover:bg-slate-100 hover:text-brand dark:text-brand-gray dark:hover:bg-white/[0.05] dark:hover:text-white'
        "
    >
        <!-- Indicador de la vista actual: no depende solo del color de fondo -->
        <span
            class="absolute inset-y-2 -left-4 w-1 rounded-r-full bg-brand transition-opacity duration-150 dark:bg-brand-gray"
            :class="active ? 'opacity-100' : 'opacity-0'"
            aria-hidden="true"
        />
        <span
            class="grid size-7 shrink-0 place-content-center rounded-md transition-colors duration-150"
            :class="
                active
                    ? 'bg-brand text-white shadow-sm shadow-brand/30 dark:bg-brand-light'
                    : 'text-slate-500 group-hover:text-brand dark:text-brand-gray dark:group-hover:text-white'
            "
        >
            <component :is="icon" class="size-4" />
        </span>
        <span class="truncate">{{ label }}</span>
    </Link>
</template>
