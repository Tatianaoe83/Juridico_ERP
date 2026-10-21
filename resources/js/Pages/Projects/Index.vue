<script setup>
import { Head } from '@inertiajs/vue3';
import { HardHat } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';

/** Catálogo de obras. Por ahora solo la pantalla: la tabla y el alta llegan después. */
const breadcrumbs = [{ label: 'Inicio', href: '/calendario' }, { label: 'Obras' }];
</script>

<template>
    <Head title="Obras" />

    <AppShell :breadcrumbs="breadcrumbs">
        <div class="flex flex-col md:h-full md:min-h-0">
            <!-- Encabezado -->
            <div class="mb-3 flex shrink-0 flex-wrap items-center justify-between gap-3 tall:mb-4">
                <div class="flex items-center gap-3">
                    <span
                        class="grid size-9 place-content-center rounded-xl bg-gradient-to-br from-brand-light to-brand text-white shadow-md shadow-brand/25 ring-1 ring-white/10"
                    >
                        <HardHat class="size-4" />
                    </span>
                    <div>
                        <h1 class="text-xl font-bold tracking-tight text-brand dark:text-white">Obras</h1>
                    </div>
                </div>
            </div>

            <div
                class="grid place-content-center rounded-2xl border border-slate-200/80 bg-white px-6 py-14 text-center shadow-[0_1px_3px_rgb(2_29_73/0.04),0_8px_24px_-12px_rgb(2_29_73/0.08)] md:min-h-0 md:flex-1 dark:border-white/[0.08] dark:bg-brand-deep dark:shadow-none"
            >
                <span class="mx-auto mb-2.5 grid size-10 place-content-center rounded-xl bg-slate-100 text-slate-400 dark:bg-white/[0.05] dark:text-brand-gray">
                    <HardHat class="size-5" />
                </span>
                <p class="text-sm font-bold text-slate-800 dark:text-white">Sin obras</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-brand-gray">Aquí van a estar las obras de las pólizas de obra.</p>
            </div>
        </div>
    </AppShell>
</template>
