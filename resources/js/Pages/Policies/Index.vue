<script setup>
import { Head } from '@inertiajs/vue3';
import { FileCheck } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';

const breadcrumbs = [{ label: 'Inicio', href: '/calendario' }, { label: 'Pólizas y Fianzas' }];
</script>

<template>
    <Head title="Pólizas y Fianzas" />

    <AppShell :breadcrumbs="breadcrumbs">
        <div class="flex flex-col md:h-full md:min-h-0">
            <!-- Encabezado -->
            <div class="mb-3 flex shrink-0 flex-wrap items-center justify-between gap-3 tall:mb-4">
                <div class="flex items-center gap-3">
                    <span
                        class="grid size-9 place-content-center rounded-xl bg-gradient-to-br from-brand-light to-brand text-white shadow-md shadow-brand/25 ring-1 ring-white/10"
                    >
                        <FileCheck class="size-4" />
                    </span>
                    <div>
                        <p class="text-[0.6rem] font-bold uppercase tracking-[0.2em] text-slate-400 dark:text-brand-gray/80">Cumplimiento</p>
                        <h1 class="text-xl font-bold tracking-tight text-brand dark:text-white">Pólizas y Fianzas</h1>
                    </div>
                </div>
            </div>
        </div>
    </AppShell>
</template>
