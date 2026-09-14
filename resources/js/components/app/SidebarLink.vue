<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { TooltipContent, TooltipPortal, TooltipRoot, TooltipTrigger } from 'reka-ui';
import { computed } from 'vue';

const props = defineProps({
    href: { type: String, required: true },
    label: { type: String, required: true },
    icon: { type: [Object, Function], required: true },
    /** Sidebar colapsado a iconos: el texto se oculta y pasa a tooltip. */
    compact: { type: Boolean, default: false },
});

const page = usePage();
const active = computed(() => page.url.split('?')[0].startsWith(props.href));
</script>

<template>
    <!--
        El tooltip va en portal: el <nav> tiene overflow y lo recortaría.
        Solo existe en modo compacto; con el texto visible sobra.
    -->
    <TooltipRoot :disabled="!compact" :delay-duration="150">
        <TooltipTrigger as-child>
            <Link
                :href="href"
                :aria-current="active ? 'page' : undefined"
                :aria-label="compact ? label : undefined"
                class="group relative flex h-10 items-center gap-3 overflow-hidden rounded-lg px-2.5 text-sm whitespace-nowrap transition-colors duration-150 focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-inset focus-visible:ring-brand/30 dark:focus-visible:ring-white/30"
                :class="
                    active
                        ? 'bg-brand/[0.07] font-semibold text-brand dark:bg-white/[0.08] dark:text-white'
                        : 'text-slate-600 hover:bg-slate-100 hover:text-brand dark:text-brand-gray dark:hover:bg-white/[0.05] dark:hover:text-white'
                "
            >
                <!-- Indicador de la vista actual: no depende solo del color de fondo -->
                <span
                    class="absolute inset-y-2 left-0 w-1 rounded-r-full bg-brand transition-opacity duration-150 dark:bg-brand-gray"
                    :class="active ? 'opacity-100' : 'opacity-0'"
                    aria-hidden="true"
                />
                <!-- El icono no se mueve al colapsar: solo se recorta el texto -->
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
                <span
                    class="truncate transition-opacity duration-200"
                    :class="compact ? 'opacity-0' : 'opacity-100'"
                >
                    {{ label }}
                </span>
            </Link>
        </TooltipTrigger>

        <TooltipPortal>
            <TooltipContent
                side="right"
                :side-offset="12"
                class="z-50 rounded-lg bg-brand px-3 py-1.5 font-corporate text-xs font-semibold text-white shadow-lg shadow-brand/30 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=delayed-open]:animate-in data-[state=delayed-open]:fade-in-0 data-[state=delayed-open]:slide-in-from-left-1 dark:bg-brand-light dark:shadow-black/40"
            >
                {{ label }}
            </TooltipContent>
        </TooltipPortal>
    </TooltipRoot>
</template>
