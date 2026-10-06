<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, FileCheck } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import PolicyForm from '@/components/app/PolicyForm.vue';

defineProps({
    /** [{ value, label, type }] */
    units: { type: Array, default: () => [] },
    /** Valores del enum `coverage`. */
    coverages: { type: Array, default: () => [] },
    /** La unidad que ya viene elegida, si llegó desde su ficha. */
    unitId: { type: Number, default: null },
});

const breadcrumbs = [
    { label: 'Inicio', href: '/calendario' },
    { label: 'Pólizas y Fianzas', href: '/polizas' },
    { label: 'Nueva póliza' },
];
</script>

<template>
    <Head title="Nueva póliza" />

    <AppShell :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-3 pb-6 tall:gap-4">
            <!-- Encabezado -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span
                        class="grid size-9 place-content-center rounded-xl bg-gradient-to-br from-brand-light to-brand text-white shadow-md shadow-brand/25 ring-1 ring-white/10"
                    >
                        <FileCheck class="size-4" />
                    </span>
                    <div>
                        <p class="text-[0.6rem] font-bold uppercase tracking-[0.2em] text-slate-400 dark:text-brand-gray/80">Pólizas y Fianzas</p>
                        <h1 class="text-xl font-bold tracking-tight text-brand dark:text-white">Nueva póliza</h1>
                    </div>
                </div>

                <Link
                    href="/polizas"
                    class="inline-flex h-9 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-[0.8rem] font-semibold text-slate-700 transition-colors duration-150 hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-500/10 dark:border-white/10 dark:bg-transparent dark:text-brand-gray dark:hover:bg-white/[0.06] dark:hover:text-white"
                >
                    <ArrowLeft class="size-4" />
                    Volver
                </Link>
            </div>

            <PolicyForm :units="units" :coverages="coverages" :unit-id="unitId" />
        </div>
    </AppShell>
</template>
