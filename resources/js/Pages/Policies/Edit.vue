<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, FileCheck, ShieldCheck } from 'lucide-vue-next';
import { computed } from 'vue';
import AppShell from '@/Layouts/AppShell.vue';
import BondForm from '@/components/app/BondForm.vue';
import PolicyForm from '@/components/app/PolicyForm.vue';

/**
 * La edición de pólizas y fianzas es una sola página: los campos dependen de
 * qué se edita. El tipo ya no cambia: una póliza no se vuelve fianza.
 */
const props = defineProps({
    /** 'policy' | 'bond' */
    type: { type: String, required: true },
    /** La póliza con sus valores tal como se capturan (solo si type = policy). */
    policy: { type: Object, default: null },
    /** La fianza con sus valores tal como se capturan (solo si type = bond). */
    bond: { type: Object, default: null },
    /** [{ value, label, type }] */
    units: { type: Array, default: () => [] },
    /** Valores del enum `coverage` por tipo de póliza. */
    coverages: { type: Object, default: () => ({}) },
    /** Catálogo: [{ id, name }] (póliza de obra y fianza). */
    businessUnits: { type: Array, default: () => [] },
    /** Valores del enum `category` (solo si type = bond). */
    categories: { type: Array, default: () => [] },
});

const isBond = computed(() => props.type === 'bond');

const number = computed(() => (isBond.value ? props.bond.bond : props.policy.policy));

const label = computed(() => (isBond.value ? 'Fianza' : 'Póliza'));

const showUrl = computed(() => (isBond.value ? `/polizas/fianzas/${props.bond.id}` : `/polizas/${props.policy.id}`));

const breadcrumbs = computed(() => [
    { label: 'Inicio', href: '/calendario' },
    { label: 'Pólizas y Fianzas', href: '/polizas' },
    { label: `${label.value} ${number.value}`, href: showUrl.value },
    { label: 'Editar' },
]);
</script>

<template>
    <Head :title="`Editar ${label.toLowerCase()} ${number}`" />

    <AppShell :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-3 pb-6 tall:gap-4">
            <!-- Encabezado -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <span
                        class="grid size-9 shrink-0 place-content-center rounded-xl bg-gradient-to-br from-brand-light to-brand text-white shadow-md shadow-brand/25 ring-1 ring-white/10"
                    >
                        <component :is="isBond ? ShieldCheck : FileCheck" class="size-4" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-[0.6rem] font-bold uppercase tracking-[0.2em] text-slate-400 dark:text-brand-gray/80">Editar {{ label.toLowerCase() }}</p>
                        <h1 class="truncate text-xl font-bold tracking-tight text-brand dark:text-white">{{ number }}</h1>
                    </div>
                </div>

                <Link
                    :href="showUrl"
                    class="inline-flex h-9 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-[0.8rem] font-semibold text-slate-700 transition-colors duration-150 hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-500/10 dark:border-white/10 dark:bg-transparent dark:text-brand-gray dark:hover:bg-white/[0.06] dark:hover:text-white"
                >
                    <ArrowLeft class="size-4" />
                    Volver
                </Link>
            </div>

            <BondForm v-if="isBond" :bond="bond" :business-units="businessUnits" :categories="categories" />
            <PolicyForm v-else :policy="policy" :units="units" :coverages="coverages" :business-units="businessUnits" />
        </div>
    </AppShell>
</template>
