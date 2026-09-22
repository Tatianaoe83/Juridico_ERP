<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Truck } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import UnitForm from '@/components/app/UnitForm.vue';
import { unitStatus } from '@/lib/units';

const props = defineProps({
    /** La unidad con sus valores tal como se capturan, más sus evidencias. */
    unit: { type: Object, required: true },
    businessUnits: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
});

const breadcrumbs = [
    { label: 'Inicio', href: '/calendario' },
    { label: 'Flotillas', href: '/flotillas' },
    { label: props.unit.policy },
];
</script>

<template>
    <Head :title="`Editar ${unit.policy}`" />

    <AppShell :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-3 pb-6 tall:gap-4">
            <!-- Encabezado: además del título, de qué unidad se trata -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span
                        class="grid size-9 place-content-center rounded-xl bg-gradient-to-br from-brand-light to-brand text-white shadow-md shadow-brand/25 ring-1 ring-white/10"
                    >
                        <Truck class="size-4" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-[0.6rem] font-bold uppercase tracking-[0.2em] text-slate-400 dark:text-brand-gray/80">Flotillas</p>
                        <h1 class="truncate text-xl font-bold tracking-tight text-brand dark:text-white">
                            {{ unit.brand }} {{ unit.model }}
                        </h1>
                    </div>
                    <span
                        class="ml-1 inline-flex items-center rounded-md px-1.5 py-0.5 text-[0.7rem] font-bold whitespace-nowrap ring-1 ring-inset"
                        :class="unitStatus(unit.status).tone"
                    >
                        {{ unitStatus(unit.status).label }}
                    </span>
                </div>

                <Link
                    href="/flotillas"
                    class="inline-flex h-9 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-[0.8rem] font-semibold text-slate-700 transition-colors duration-150 hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-500/10 dark:border-white/10 dark:bg-transparent dark:text-brand-gray dark:hover:bg-white/[0.06] dark:hover:text-white"
                >
                    <ArrowLeft class="size-4" />
                    Volver
                </Link>
            </div>

            <UnitForm :unit="unit" :business-units="businessUnits" :statuses="statuses" />
        </div>
    </AppShell>
</template>
