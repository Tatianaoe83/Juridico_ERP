<script setup>
import SidebarLink from '@/components/app/SidebarLink.vue';

/**
 * Grupo fijo del menú: siempre muestra sus enlaces, no se pliega. Con pocas
 * entradas, plegar solo esconde opciones sin ahorrar espacio.
 */
defineProps({
    label: { type: String, required: true },
    items: { type: Array, required: true },
    /** Sidebar colapsado a iconos: el título se vuelve una rayita. */
    compact: { type: Boolean, default: false },
});
</script>

<template>
    <div class="space-y-1">
        <!-- Alto fijo: el título y la rayita del modo iconos ocupan lo mismo y nada salta -->
        <div class="relative flex h-6 items-center">
            <p
                class="px-3 text-[0.65rem] font-semibold uppercase tracking-[0.15em] whitespace-nowrap text-muted-foreground transition-opacity duration-200 dark:text-brand-gray/70"
                :class="compact ? 'opacity-0' : 'opacity-100'"
                :aria-hidden="compact"
            >
                {{ label }}
            </p>
            <span
                class="pointer-events-none absolute left-3 h-px w-6 bg-slate-200 transition-opacity duration-200 dark:bg-white/10"
                :class="compact ? 'opacity-100' : 'opacity-0'"
                aria-hidden="true"
            />
        </div>

        <div class="space-y-1">
            <SidebarLink
                v-for="item in items"
                :key="item.href"
                :href="item.href"
                :label="item.label"
                :icon="item.icon"
                :compact="compact"
            />
        </div>
    </div>
</template>
