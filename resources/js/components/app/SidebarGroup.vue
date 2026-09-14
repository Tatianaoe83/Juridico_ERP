<script setup>
import { usePage } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import SidebarLink from '@/components/app/SidebarLink.vue';

const props = defineProps({
    label: { type: String, required: true },
    items: { type: Array, required: true },
    /** false = encabezado suelto, sin plegar. */
    collapsible: { type: Boolean, default: false },
});

const page = usePage();

const holdsCurrent = computed(() => {
    const path = page.url.split('?')[0];

    return props.items.some((item) => path.startsWith(item.href));
});

const KEY = `sidebar:${props.label}`;

// Abierto por defecto; se recuerda entre visitas.
const open = ref(localStorage.getItem(KEY) !== 'closed');

watch(open, (value) => localStorage.setItem(KEY, value ? 'open' : 'closed'));

// Entrar a una vista de dentro despliega el grupo: dejarlo cerrado con la
// página activa adentro haría parecer que el menú no responde.
watch(holdsCurrent, (inside) => {
    if (inside) open.value = true;
}, { immediate: true });
</script>

<template>
    <div class="space-y-1">
        <button
            v-if="collapsible"
            type="button"
            class="flex w-full cursor-pointer items-center gap-1.5 rounded-md px-3 py-1 text-[0.65rem] font-semibold uppercase tracking-[0.15em] text-muted-foreground transition-colors hover:text-brand dark:text-brand-gray/70 dark:hover:text-white"
            :aria-expanded="open"
            @click="open = !open"
        >
            <ChevronRight
                class="size-3 shrink-0 transition-transform duration-150 motion-reduce:transition-none"
                :class="open && 'rotate-90'"
            />
            <span>{{ label }}</span>
            <!-- Señala que hay algo activo dentro cuando está plegado -->
            <span v-if="!open && holdsCurrent" class="ml-auto size-1.5 rounded-full bg-brand dark:bg-brand-gray" />
        </button>

        <p
            v-else
            class="px-3 pb-1 text-[0.65rem] font-semibold uppercase tracking-[0.15em] text-muted-foreground dark:text-brand-gray/70"
        >
            {{ label }}
        </p>

        <div v-show="!collapsible || open" class="space-y-1">
            <SidebarLink
                v-for="item in items"
                :key="item.href"
                :href="item.href"
                :label="item.label"
                :icon="item.icon"
            />
        </div>
    </div>
</template>
