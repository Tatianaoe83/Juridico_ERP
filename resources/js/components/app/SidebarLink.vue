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
        class="flex items-center gap-2.5 rounded-md px-2.5 py-2 text-sm transition-colors"
        :class="
            active
                ? 'bg-accent font-medium text-accent-foreground'
                : 'text-muted-foreground hover:bg-accent/60 hover:text-foreground'
        "
    >
        <component :is="icon" class="size-4 shrink-0" />
        <span class="truncate">{{ label }}</span>
    </Link>
</template>
