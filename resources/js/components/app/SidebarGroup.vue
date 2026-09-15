<script setup>
import { ChevronDown } from 'lucide-vue-next';
import SidebarLink from '@/components/app/SidebarLink.vue';

/**
 * Grupo plegable del menú. Quién está abierto lo decide AppShell: solo uno a
 * la vez, así que el grupo avisa con `toggle` y no guarda su propio estado.
 */
const props = defineProps({
    label: { type: String, required: true },
    items: { type: Array, required: true },
    open: { type: Boolean, default: false },
    /** Sidebar colapsado a iconos: el título se vuelve una rayita y no se pliega. */
    compact: { type: Boolean, default: false },
});

const emit = defineEmits(['toggle']);

// Los nombres de grupo no se repiten: bastan para ligar el botón con su lista.
const panelId = `sidebar-group-${props.label.normalize('NFD').replace(/[^\w]+/g, '-').toLowerCase()}`;
</script>

<template>
    <div class="space-y-1">
        <!-- Alto fijo: el título y la rayita del modo iconos ocupan lo mismo y nada salta -->
        <div class="relative flex h-7 items-center">
            <button
                type="button"
                class="flex h-full w-full cursor-pointer items-center gap-2 rounded-md px-3 text-[0.65rem] font-semibold uppercase tracking-[0.15em] whitespace-nowrap text-muted-foreground transition-[opacity,color] duration-200 hover:text-brand focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-inset focus-visible:ring-brand/30 dark:text-brand-gray/70 dark:hover:text-white dark:focus-visible:ring-white/30"
                :class="compact ? 'pointer-events-none opacity-0' : 'opacity-100'"
                :aria-hidden="compact"
                :tabindex="compact ? -1 : undefined"
                :aria-expanded="open"
                :aria-controls="panelId"
                @click="emit('toggle')"
            >
                <span class="flex-1 text-left">{{ label }}</span>
                <ChevronDown class="size-3.5 shrink-0 transition-transform duration-200" :class="open ? 'rotate-0' : '-rotate-90'" />
            </button>
            <span
                class="pointer-events-none absolute left-3 h-px w-6 bg-slate-200 transition-opacity duration-200 dark:bg-white/10"
                :class="compact ? 'opacity-100' : 'opacity-0'"
                aria-hidden="true"
            />
        </div>

        <!--
            Se pliega animando grid-template-rows (0fr ↔ 1fr): anima hasta el alto
            real sin medirlo. En modo iconos todo queda abierto: sin títulos no
            habría forma de desplegar un grupo.
        -->
        <div
            :id="panelId"
            class="grid transition-[grid-template-rows] duration-300 ease-[cubic-bezier(0.32,0.72,0,1)] motion-reduce:transition-none"
            :class="open || compact ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
            :inert="!open && !compact"
        >
            <div class="min-h-0 space-y-1 overflow-hidden">
                <SidebarLink
                    v-for="item in props.items"
                    :key="item.href"
                    :href="item.href"
                    :label="item.label"
                    :icon="item.icon"
                    :compact="compact"
                />
            </div>
        </div>
    </div>
</template>
