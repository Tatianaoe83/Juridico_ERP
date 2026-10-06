<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, FileCheck, ShieldCheck } from 'lucide-vue-next';
import { computed, KeepAlive, ref } from 'vue';
import AppShell from '@/Layouts/AppShell.vue';
import BondForm from '@/components/app/BondForm.vue';
import PolicyForm from '@/components/app/PolicyForm.vue';

/**
 * El alta de pólizas y fianzas es una sola página: arriba se elige qué se
 * registra y abajo cambian los campos. Cambiar de tipo no borra lo capturado
 * en el otro.
 */
const props = defineProps({
    /** 'policy' | 'bond': con cuál abre. */
    type: { type: String, default: 'policy' },
    /** [{ value, label, type }] */
    units: { type: Array, default: () => [] },
    /** Valores del enum `coverage`. */
    coverages: { type: Array, default: () => [] },
    /** La unidad que ya viene elegida, si llegó desde su ficha. */
    unitId: { type: Number, default: null },
});

/** Las dos opciones del selector. */
const OPTIONS = [
    { value: 'policy', label: 'Póliza', icon: FileCheck },
    { value: 'bond', label: 'Fianza', icon: ShieldCheck },
];

const current = ref(props.type === 'bond' ? 'bond' : 'policy');

const selected = computed(() => OPTIONS.find((option) => option.value === current.value));

const title = computed(() => (current.value === 'bond' ? 'Nueva fianza' : 'Nueva póliza'));

const breadcrumbs = computed(() => [
    { label: 'Inicio', href: '/calendario' },
    { label: 'Pólizas y Fianzas', href: '/polizas' },
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
                        <component :is="selected.icon" class="size-4" />
                    </span>
                    <div>
                        <p class="text-[0.6rem] font-bold uppercase tracking-[0.2em] text-slate-400 dark:text-brand-gray/80">Pólizas y Fianzas</p>
                        <h1 class="text-xl font-bold tracking-tight text-brand dark:text-white">{{ title }}</h1>
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

            <!--
                Qué se registra: un control segmentado; la pastilla se desliza a la
                opción elegida. Solo se animan la pastilla y el formulario.
            -->
            <div
                class="relative grid w-full grid-cols-2 gap-1 rounded-xl bg-slate-100 p-1 ring-1 ring-inset ring-slate-200/70 sm:w-80 dark:bg-white/[0.05] dark:ring-white/[0.06]"
                role="radiogroup"
                aria-label="Tipo de registro"
            >
                <span
                    aria-hidden="true"
                    class="pointer-events-none absolute top-1 bottom-1 left-1 w-[calc(50%-0.375rem)] rounded-lg bg-white shadow-sm ring-1 ring-slate-900/5 transition-transform duration-200 ease-out motion-reduce:transition-none dark:bg-white/15 dark:ring-white/10"
                    :class="current === 'bond' ? 'translate-x-[calc(100%+0.25rem)]' : 'translate-x-0'"
                />

                <button
                    v-for="option in OPTIONS"
                    :key="option.value"
                    type="button"
                    role="radio"
                    :aria-checked="current === option.value"
                    class="relative z-[1] inline-flex h-9 cursor-pointer items-center justify-center gap-2 rounded-lg text-[0.8rem] font-semibold transition-colors duration-200 focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-brand/25"
                    :class="current === option.value ? 'text-brand dark:text-white' : 'text-slate-500 hover:text-slate-800 dark:text-brand-gray dark:hover:text-white'"
                    @click="current = option.value"
                >
                    <component :is="option.icon" class="size-4" />
                    {{ option.label }}
                </button>
            </div>

            <!-- Fundido corto al cambiar; KeepAlive guarda lo capturado en el otro -->
            <Transition
                mode="out-in"
                enter-active-class="transition duration-200 ease-out motion-reduce:transition-none"
                enter-from-class="opacity-0 translate-y-1"
                leave-active-class="transition duration-150 ease-in motion-reduce:transition-none"
                leave-to-class="opacity-0"
            >
                <KeepAlive>
                    <BondForm v-if="current === 'bond'" key="bond" />
                    <PolicyForm v-else key="policy" :units="units" :coverages="coverages" :unit-id="unitId" />
                </KeepAlive>
            </Transition>
        </div>
    </AppShell>
</template>
