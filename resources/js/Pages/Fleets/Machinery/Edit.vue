<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Tractor } from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import MachineryForm from '@/components/app/MachineryForm.vue';
import { fleetListUrl } from '@/lib/units';

const props = defineProps({
    /** La póliza con sus valores tal como se capturan y sus máquinas con sus documentos. */
    policy: { type: Object, required: true },
    businessUnits: { type: Array, default: () => [] },
    insurers: { type: Array, default: () => [] },
    beneficiaries: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    machineryCoverages: { type: Array, default: () => [] },
    machineryCurrencies: { type: Object, default: () => ({}) },
    /** Monedas que se pueden elegir para el pago. */
    currencies: { type: Array, default: () => [] },
});

const breadcrumbs = [
    { label: 'Inicio', href: '/calendario' },
    { label: 'Flotillas', href: fleetListUrl(props.policy.source, true) },
    { label: `Póliza ${props.policy.policy}`, href: `/flotillas/maquinaria/${props.policy.id}` },
    { label: 'Editar' },
];
</script>

<template>
    <Head :title="`Editar póliza ${policy.policy}`" />

    <AppShell :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-3 pb-6 tall:gap-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <span
                        class="grid size-9 shrink-0 place-content-center rounded-xl bg-gradient-to-br from-brand-light to-brand text-white shadow-md shadow-brand/25 ring-1 ring-white/10"
                    >
                        <Tractor class="size-4" />
                    </span>
                    <div class="min-w-0">
                        <h1 class="truncate text-xl font-bold tracking-tight text-brand dark:text-white">{{ policy.policy }}</h1>
                    </div>
                </div>

                <Link
                    :href="`/flotillas/maquinaria/${policy.id}`"
                    class="inline-flex h-9 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-[0.8rem] font-semibold text-slate-700 transition-colors duration-150 hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-500/10 dark:border-white/10 dark:bg-transparent dark:text-brand-gray dark:hover:bg-white/[0.06] dark:hover:text-white"
                >
                    <ArrowLeft class="size-4" />
                    Volver
                </Link>
            </div>

            <MachineryForm
                :policy="policy"
                :business-units="businessUnits"
                :insurers="insurers"
                :beneficiaries="beneficiaries"
                :statuses="statuses"
                :coverages="machineryCoverages"
                :currencies="machineryCurrencies"
                :currency-codes="currencies"
            />
        </div>
    </AppShell>
</template>
