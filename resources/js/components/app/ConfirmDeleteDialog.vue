<script setup>
import { TriangleAlert, Trash2 } from 'lucide-vue-next';
import {
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogOverlay,
    AlertDialogPortal,
    AlertDialogRoot,
    AlertDialogTitle,
} from 'reka-ui';

/**
 * Confirmación de borrado, la misma en toda la app: solo cambia el texto.
 *
 * AlertDialog y no Dialog: no se cierra al hacer clic fuera, así que un clic
 * perdido no confirma ni descarta nada; se sale con «Cancelar» o Esc. Al
 * abrir, el foco cae en «Cancelar»: un Enter por inercia no borra. Quien lo
 * usa escucha `confirm` y hace el borrado; el modal se cierra solo.
 */
defineProps({
    open: { type: Boolean, default: false },
    text: { type: String, required: true },
});

const emit = defineEmits(['update:open', 'confirm']);
</script>

<template>
    <AlertDialogRoot :open="open" @update:open="emit('update:open', $event)">
        <AlertDialogPortal>
            <AlertDialogOverlay
                class="fixed inset-0 z-50 bg-brand-deep/60 backdrop-blur-sm data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:duration-150 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:duration-200 dark:bg-black/70"
            />
            <AlertDialogContent
                class="fixed top-1/2 left-1/2 z-50 w-[calc(100%-2rem)] max-w-md -translate-x-1/2 -translate-y-1/2 overflow-hidden rounded-2xl bg-white font-corporate shadow-2xl shadow-brand-deep/25 ring-1 ring-slate-900/[0.06] outline-none data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[state=closed]:duration-150 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95 data-[state=open]:slide-in-from-bottom-2 data-[state=open]:duration-200 motion-reduce:animate-none dark:bg-brand-panel dark:shadow-black/60 dark:ring-white/10"
            >
                <!-- Resplandor rojo arriba: marca el tono de la acción sin gritar -->
                <div
                    class="pointer-events-none absolute inset-x-0 top-0 h-28 bg-gradient-to-b from-red-500/[0.07] to-transparent dark:from-red-500/[0.12]"
                    aria-hidden="true"
                />

                <div class="relative flex gap-4 px-6 pt-6 pb-5">
                    <!-- Icono con halos: se lee de un vistazo que es una acción destructiva -->
                    <span class="relative grid size-11 shrink-0 place-content-center" aria-hidden="true">
                        <span class="absolute inset-0 rounded-xl bg-red-500/10 ring-1 ring-inset ring-red-500/20 dark:bg-red-500/15 dark:ring-red-400/25" />
                        <span class="absolute -inset-1.5 rounded-2xl ring-1 ring-red-500/[0.08] dark:ring-red-400/10" />
                        <Trash2 class="relative size-5 text-red-600 dark:text-red-400" />
                    </span>

                    <div class="min-w-0 flex-1 pt-0.5">
                        <AlertDialogTitle class="text-base font-bold tracking-tight text-slate-900 dark:text-white">
                            Confirmar eliminación
                        </AlertDialogTitle>
                        <AlertDialogDescription class="mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                            {{ text }}
                        </AlertDialogDescription>

                        <p
                            class="mt-3 inline-flex items-center gap-1.5 rounded-md bg-amber-50 px-2 py-1 text-[0.7rem] font-semibold text-amber-800 ring-1 ring-inset ring-amber-600/15 dark:bg-amber-400/10 dark:text-amber-200 dark:ring-amber-400/20"
                        >
                            <TriangleAlert class="size-3.5" />
                            Esta acción no se puede deshacer
                        </p>
                    </div>
                </div>

                <div
                    class="flex flex-col-reverse gap-2 border-t border-slate-100 bg-slate-50/80 px-6 py-3.5 sm:flex-row sm:justify-end dark:border-white/[0.06] dark:bg-white/[0.02]"
                >
                    <AlertDialogCancel
                        class="h-10 cursor-pointer rounded-xl border border-slate-200 bg-white px-4 text-[0.8rem] font-semibold text-slate-700 shadow-sm transition-colors duration-150 hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/15 dark:border-white/10 dark:bg-white/[0.03] dark:text-slate-200 dark:shadow-none dark:hover:bg-white/[0.07] dark:hover:text-white"
                    >
                        Cancelar
                    </AlertDialogCancel>
                    <AlertDialogAction
                        class="inline-flex h-10 cursor-pointer items-center justify-center gap-2 rounded-xl bg-red-600 px-4 text-[0.8rem] font-semibold text-white shadow-md shadow-red-600/25 ring-1 ring-inset ring-white/10 transition-all duration-150 hover:bg-red-700 hover:shadow-lg hover:shadow-red-600/30 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-500/30 active:scale-[0.98] motion-reduce:active:scale-100 dark:bg-red-500 dark:shadow-black/40 dark:hover:bg-red-600"
                        @click="emit('confirm')"
                    >
                        <Trash2 class="size-4" />
                        Eliminar
                    </AlertDialogAction>
                </div>
            </AlertDialogContent>
        </AlertDialogPortal>
    </AlertDialogRoot>
</template>
