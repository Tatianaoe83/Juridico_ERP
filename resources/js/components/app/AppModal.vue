<script setup>
import { X } from 'lucide-vue-next';
import { DialogClose, DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'reka-ui';

/**
 * Modal de la app, armado con las primitivas de reka-ui y no con el Dialog
 * de shadcn: aquel trae márgenes negativos en el pie y un velo casi
 * transparente pensados para su propio relleno, y cada ajuste se volvía una
 * pelea de clases.
 *
 * Cabecera y pie quedan fijos; solo el cuerpo desplaza si no cabe.
 */
defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    /** Componente de icono de Lucide para la cabecera. */
    icon: { type: [Object, Function], default: null },
    size: { type: String, default: 'md' },
    /** La cabecera propia es oscura (una portada azul): la X va en blanco. */
    closeOnDark: { type: Boolean, default: false },
});

const emit = defineEmits(['update:open']);

const SIZES = {
    sm: 'sm:max-w-md',
    md: 'sm:max-w-xl',
    lg: 'sm:max-w-2xl',
};
</script>

<template>
    <DialogRoot :open="open" @update:open="emit('update:open', $event)">
        <DialogPortal>
            <DialogOverlay
                class="fixed inset-0 z-50 bg-brand-deep/55 backdrop-blur-[3px] data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0 dark:bg-black/60"
            />
            <DialogContent
                class="fixed top-1/2 left-1/2 z-50 flex max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] -translate-x-1/2 -translate-y-1/2 flex-col overflow-hidden rounded-2xl bg-white font-corporate text-foreground shadow-2xl shadow-brand-deep/30 ring-1 ring-slate-900/5 outline-none duration-200 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95 data-[state=open]:slide-in-from-bottom-2 motion-reduce:animate-none dark:bg-brand-panel dark:shadow-black/50 dark:ring-white/10"
                :class="SIZES[size]"
            >
                <!-- Cabecera: se puede reemplazar entera con el slot `header` -->
                <slot name="header">
                    <div class="flex shrink-0 items-start gap-4 px-6 pt-6 pb-5 sm:px-7 sm:pt-7 short:pt-5 short:pb-3">
                        <span
                            v-if="icon"
                            class="grid size-12 shrink-0 place-content-center rounded-xl short:size-10 bg-gradient-to-br from-brand-light to-brand text-white shadow-lg shadow-brand/25 ring-1 ring-white/10"
                        >
                            <component :is="icon" class="size-5" />
                        </span>
                        <div class="min-w-0 flex-1 pt-0.5 pr-8">
                            <DialogTitle class="text-lg font-bold tracking-tight text-brand dark:text-white">
                                {{ title }}
                            </DialogTitle>
                            <DialogDescription v-if="description" class="mt-1 text-sm leading-relaxed text-slate-500 dark:text-brand-gray">
                                {{ description }}
                            </DialogDescription>
                        </div>
                    </div>
                </slot>

                <DialogClose
                    class="absolute top-4 right-4 z-10 grid size-9 cursor-pointer place-content-center rounded-lg transition-colors duration-150 focus-visible:outline-none focus-visible:ring-[3px]"
                    :class="
                        closeOnDark
                            ? 'text-white/75 hover:bg-white/15 hover:text-white focus-visible:ring-white/30'
                            : 'text-slate-400 hover:bg-slate-100 hover:text-slate-700 focus-visible:ring-brand/25 dark:text-brand-gray dark:hover:bg-white/10 dark:hover:text-white'
                    "
                    aria-label="Cerrar"
                >
                    <X class="size-[1.1rem]" />
                </DialogClose>

                <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain">
                    <slot />
                </div>

                <div
                    v-if="$slots.footer"
                    class="flex shrink-0 flex-col-reverse gap-2.5 border-t border-slate-100 bg-slate-50/80 px-6 py-4 short:py-3 sm:flex-row sm:items-center sm:justify-end sm:px-7 dark:border-white/[0.06] dark:bg-white/[0.02]"
                >
                    <slot name="footer" />
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
