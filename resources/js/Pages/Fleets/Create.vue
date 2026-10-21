<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Truck } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppShell from '@/Layouts/AppShell.vue';
import FormSelect from '@/components/app/FormSelect.vue';
import MachineryForm from '@/components/app/MachineryForm.vue';
import UnitForm from '@/components/app/UnitForm.vue';
import { UNIT_TYPES } from '@/lib/units';

/**
 * El alta empieza por el tipo: el automóvil se da de alta solo, con su póliza;
 * la maquinaria se da de alta por póliza, con todas las máquinas que cubre.
 */
const props = defineProps({
    /** Con qué tipo abre; null = hay que elegirlo. */
    type: { type: String, default: null },
    /** 'corporate' | 'supplier': con qué origen abre la póliza. */
    source: { type: String, default: 'corporate' },
    /** Valores del enum `type`: automóvil o maquinaria. */
    types: { type: Array, default: () => [] },
    /** Catálogo: [{ id, name }]. Puede venir vacío si nadie lo ha llenado. */
    businessUnits: { type: Array, default: () => [] },
    insurers: { type: Array, default: () => [] },
    beneficiaries: { type: Array, default: () => [] },
    /** Valores del enum `status`, tal como los acepta el servidor. */
    statuses: { type: Array, default: () => [] },
    /** Coberturas de la póliza vehicular. */
    coverages: { type: Array, default: () => [] },
    /** Coberturas de la póliza de maquinaria: EC y RC. */
    machineryCoverages: { type: Array, default: () => [] },
    /** { cobertura: moneda } de la maquinaria. */
    machineryCurrencies: { type: Object, default: () => ({}) },
    /** Monedas que se pueden elegir para el pago. */
    currencies: { type: Array, default: () => [] },
});

const type = ref(props.type ?? '');

const typeOptions = computed(() => props.types.map((value) => ({ value, label: (UNIT_TYPES[value]?.label ?? value).toUpperCase() })));

const title = computed(() => (type.value === 'machinery' ? 'Nueva póliza de maquinaria' : 'Nueva unidad'));

/** Volver a la pestaña de donde vino. */
const back = props.source === 'supplier' ? '/flotillas?tab=supplier' : '/flotillas';

const breadcrumbs = computed(() => [
    { label: 'Inicio', href: '/dashboard' },
    { label: 'Flotillas', href: back },
    { label: title.value },
]);
</script>

<template>
    <Head :title="title" />

    <AppShell :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-3 pb-6 tall:gap-4">
            <!-- Encabezado -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span
                        class="grid size-9 place-content-center rounded-xl bg-gradient-to-br from-brand-light to-brand text-white shadow-md shadow-brand/25 ring-1 ring-white/10"
                    >
                        <component :is="UNIT_TYPES[type]?.icon ?? Truck" class="size-4" />
                    </span>
                    <div>
                        <h1 class="text-xl font-bold tracking-tight text-brand dark:text-white">{{ title }}</h1>
                    </div>
                </div>

                <Link
                    :href="back"
                    class="inline-flex h-9 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-[0.8rem] font-semibold text-slate-700 transition-colors duration-150 hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-500/10 dark:border-white/10 dark:bg-transparent dark:text-brand-gray dark:hover:bg-white/[0.06] dark:hover:text-white"
                >
                    <ArrowLeft class="size-4" />
                    Volver
                </Link>
            </div>

            <!-- Tipo de unidad: decide qué formulario sigue -->
            <section
                class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-[0_1px_3px_rgb(2_29_73/0.04),0_8px_24px_-12px_rgb(2_29_73/0.08)] tall:p-5 dark:border-white/[0.08] dark:bg-brand-deep dark:shadow-none"
            >
                <div class="max-w-sm">
                    <label for="unit-type" class="mb-1.5 block text-[0.75rem] font-semibold text-slate-700 dark:text-slate-200">
                        Tipo de unidad
                        <span class="font-normal text-red-500 dark:text-red-400">*</span>
                    </label>
                    <FormSelect id="unit-type" v-model="type" :options="typeOptions" :icon="UNIT_TYPES[type]?.icon ?? Truck" placeholder="Selecciona un tipo" />
                    <p v-if="!type" class="mt-1 text-[0.7rem] text-slate-400 dark:text-brand-gray/70">
                        Automóvil: una unidad con su póliza. Maquinaria: una póliza con todas sus máquinas.
                    </p>
                </div>
            </section>

            <UnitForm v-if="type === 'vehicle'" :business-units="businessUnits" :insurers="insurers" :beneficiaries="beneficiaries" :statuses="statuses" :coverages="coverages" :initial-source="source" />
            <MachineryForm
                v-else-if="type === 'machinery'"
                :business-units="businessUnits"
                :insurers="insurers"
                :beneficiaries="beneficiaries"
                :statuses="statuses"
                :coverages="machineryCoverages"
                :currencies="machineryCurrencies"
                :currency-codes="currencies"
                :initial-source="source"
            />
        </div>
    </AppShell>
</template>
