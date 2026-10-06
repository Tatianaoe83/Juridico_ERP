<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Truck } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import UnitForm from '@/components/app/UnitForm.vue';

defineProps({
    /** Catálogo: [{ id, name }]. Puede venir vacío si nadie lo ha llenado. */
    businessUnits: { type: Array, default: () => [] },
    /** Valores del enum `status`, tal como los acepta el servidor. */
    statuses: { type: Array, default: () => [] },
    /** Valores del enum `type`: vehículo o maquinaria. */
    types: { type: Array, default: () => [] },
});

const breadcrumbs = [
    { label: 'Inicio', href: '/calendario' },
    { label: 'Flotillas', href: '/flotillas' },
    { label: 'Nueva unidad' },
];
</script>

<template>
    <Head title="Nueva unidad" />

    <AppShell :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-3 pb-6 tall:gap-4">
            <!-- Encabezado -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span
                        class="grid size-9 place-content-center rounded-xl bg-gradient-to-br from-brand-light to-brand text-white shadow-md shadow-brand/25 ring-1 ring-white/10"
                    >
                        <Truck class="size-4" />
                    </span>
                    <div>
                        <p class="text-[0.6rem] font-bold uppercase tracking-[0.2em] text-slate-400 dark:text-brand-gray/80">Flotillas</p>
                        <h1 class="text-xl font-bold tracking-tight text-brand dark:text-white">Nueva unidad</h1>
                    </div>
                </div>

                <Link
                    href="/flotillas"
                    class="inline-flex h-9 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-[0.8rem] font-semibold text-slate-700 transition-colors duration-150 hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-500/10 dark:border-white/10 dark:bg-transparent dark:text-brand-gray dark:hover:bg-white/[0.06] dark:hover:text-white"
                >
                    <ArrowLeft class="size-4" />
                    Volver
                </Link>
            </div>

            <UnitForm :business-units="businessUnits" :statuses="statuses" :types="types" />
        </div>
    </AppShell>
</template>
