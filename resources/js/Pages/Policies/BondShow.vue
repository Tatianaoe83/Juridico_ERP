<script setup>
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Ban,
    Building,
    CalendarCheck,
    CalendarClock,
    CalendarPlus,
    CalendarRange,
    FileText,
    Link2,
    MessageSquareText,
    Package,
    ShieldCheck,
    UserRound,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppShell from '@/Layouts/AppShell.vue';
import { COVERAGE_TYPES, coverageStatus } from '@/lib/coverages';
import { money, shortDate } from '@/lib/units';

const props = defineProps({
    /** La fianza completa. */
    bond: { type: Object, required: true },
});

const breadcrumbs = [
    { label: 'Inicio', href: '/calendario' },
    { label: 'Pólizas y Fianzas', href: '/polizas' },
    { label: `Fianza ${props.bond.bond}` },
];

const status = computed(() => coverageStatus(props.bond.status));

/** «15 ene 2026 – 15 ene 2027»; con una sola fecha, esa. */
function range(from, to) {
    const start = shortDate(from);
    const end = shortDate(to);

    if (!start && !end) return null;

    return start && end ? `${start} – ${end}` : (start ?? end);
}

/** Quién la da, a quién y sobre qué. */
const details = computed(() => [
    { label: 'Fianza', value: props.bond.bond, icon: ShieldCheck },
    { label: 'Afianzadora', value: props.bond.bonding_company, icon: Building },
    { label: 'Beneficiario', value: props.bond.beneficiary, icon: UserRound },
    { label: 'Producto', value: props.bond.product, icon: Package },
    { label: 'Relativo', value: props.bond.related, icon: Link2 },
    { label: 'Docto. fuente', value: props.bond.source_document, icon: FileText },
]);

/** Las fechas en el orden en que pasan: se pide, se emite y corre la vigencia. */
const dates = computed(() => [
    { label: 'Solicitud de emisión', value: shortDate(props.bond.requested_on), icon: CalendarPlus },
    { label: 'Fecha de emisión', value: shortDate(props.bond.issued_on), icon: CalendarCheck },
    { label: 'Vigencia', value: range(props.bond.valid_from, props.bond.valid_until), icon: CalendarRange },
]);

const cancellation = computed(() => props.bond.cancellation_requested_on || props.bond.cancelled_on);

/** «22 sep 2026, 14:30» para las marcas de tiempo del registro. */
function dateTime(iso) {
    if (!iso) return '—';

    return new Date(iso).toLocaleString('es-MX', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

const CARD =
    'rounded-2xl border border-slate-200/80 bg-white p-4 shadow-[0_1px_3px_rgb(2_29_73/0.04),0_8px_24px_-12px_rgb(2_29_73/0.08)] ' +
    'tall:p-5 dark:border-white/[0.08] dark:bg-brand-deep dark:shadow-none';

const SECTION_TITLE = 'text-[0.62rem] font-bold uppercase tracking-[0.14em] text-slate-400 dark:text-brand-gray/80';

const TILE_ICON =
    'mt-0.5 grid size-7 shrink-0 place-content-center rounded-lg bg-slate-50 text-slate-400 ring-1 ring-inset ring-slate-500/10 dark:bg-white/[0.05] dark:text-brand-gray dark:ring-white/10';

const DT = 'text-[0.6rem] font-bold uppercase tracking-[0.12em] text-slate-400 dark:text-brand-gray/70';

const DD = 'truncate text-[0.82rem] font-semibold text-slate-800 dark:text-white';
</script>

<template>
    <Head :title="`Fianza ${bond.bond}`" />

    <AppShell :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-3 pb-6 tall:gap-4">
            <!-- Encabezado: qué fianza es y cómo está -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex min-w-0 flex-wrap items-center gap-3">
                    <span
                        class="grid size-9 shrink-0 place-content-center rounded-xl bg-gradient-to-br from-brand-light to-brand text-white shadow-md shadow-brand/25 ring-1 ring-white/10"
                    >
                        <ShieldCheck class="size-4" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-[0.6rem] font-bold uppercase tracking-[0.2em] text-slate-400 dark:text-brand-gray/80">{{ COVERAGE_TYPES.bond.label }}</p>
                        <h1 class="truncate text-xl font-bold tracking-tight text-brand dark:text-white">{{ bond.bond }}</h1>
                    </div>
                    <span class="inline-flex shrink-0 items-center gap-1 rounded-md px-1.5 py-0.5 text-[0.7rem] font-bold whitespace-nowrap ring-1 ring-inset" :class="status.tone">
                        <component :is="status.icon" class="size-3.5" />
                        {{ status.label }}
                    </span>
                </div>

                <Link
                    href="/polizas"
                    class="inline-flex h-9 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-[0.8rem] font-semibold text-slate-700 transition-colors duration-150 hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-500/10 dark:border-white/10 dark:bg-transparent dark:text-brand-gray dark:hover:bg-white/[0.06] dark:hover:text-white"
                >
                    <ArrowLeft class="size-4" />
                    Volver
                </Link>
            </div>

            <div class="grid gap-3 tall:gap-4 lg:grid-cols-3">
                <!-- Columna principal -->
                <div class="flex flex-col gap-3 tall:gap-4 lg:col-span-2">
                    <section :class="CARD">
                        <p :class="SECTION_TITLE">Datos de la fianza</p>

                        <dl class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            <div v-for="detail in details" :key="detail.label" class="flex items-start gap-2.5">
                                <span :class="TILE_ICON"><component :is="detail.icon" class="size-3.5" /></span>
                                <div class="min-w-0">
                                    <dt :class="DT">{{ detail.label }}</dt>
                                    <dd :class="DD" :title="detail.value ?? undefined">{{ detail.value ?? '—' }}</dd>
                                </div>
                            </div>
                        </dl>
                    </section>

                    <section :class="CARD">
                        <p :class="SECTION_TITLE">Emisión y vigencia</p>

                        <dl class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            <div v-for="detail in dates" :key="detail.label" class="flex items-start gap-2.5">
                                <span :class="TILE_ICON"><component :is="detail.icon" class="size-3.5" /></span>
                                <div class="min-w-0">
                                    <dt :class="DT">{{ detail.label }}</dt>
                                    <dd :class="[DD, 'tabular-nums']">{{ detail.value ?? '—' }}</dd>
                                </div>
                            </div>
                        </dl>
                    </section>

                    <section :class="CARD">
                        <p :class="SECTION_TITLE">Comentarios</p>

                        <p v-if="bond.comments" class="mt-3 text-[0.8rem] leading-relaxed whitespace-pre-line text-slate-700 dark:text-slate-200">
                            {{ bond.comments }}
                        </p>
                        <p v-else class="mt-3 flex items-center gap-2 text-[0.78rem] text-slate-400 dark:text-brand-gray/70">
                            <MessageSquareText class="size-4" />
                            Sin comentarios.
                        </p>
                    </section>
                </div>

                <!-- Columna lateral -->
                <div class="flex flex-col gap-3 tall:gap-4">
                    <section :class="CARD">
                        <p :class="SECTION_TITLE">Monto afianzado</p>

                        <p class="mt-3 text-2xl font-bold tabular-nums text-brand dark:text-white">
                            {{ bond.amount !== null ? money(bond.amount) : '—' }}
                        </p>
                        <p class="text-[0.7rem] text-slate-400 dark:text-brand-gray/70">Lo que garantiza ante el beneficiario</p>
                    </section>

                    <!-- Cancelación: solo si se pidió -->
                    <section v-if="cancellation" :class="CARD">
                        <p :class="SECTION_TITLE">Cancelación</p>

                        <dl class="mt-3 flex flex-col gap-3">
                            <div class="flex items-start gap-2.5">
                                <span :class="TILE_ICON"><CalendarClock class="size-3.5" /></span>
                                <div class="min-w-0">
                                    <dt :class="DT">Solicitud de cancelación</dt>
                                    <dd :class="DD">{{ shortDate(bond.cancellation_requested_on) ?? '—' }}</dd>
                                </div>
                            </div>
                            <div class="flex items-start gap-2.5">
                                <span :class="TILE_ICON"><Ban class="size-3.5" /></span>
                                <div class="min-w-0">
                                    <dt :class="DT">Fecha de cancelación</dt>
                                    <dd :class="DD">{{ shortDate(bond.cancelled_on) ?? 'Pendiente' }}</dd>
                                </div>
                            </div>
                        </dl>
                    </section>

                    <section :class="CARD">
                        <p :class="SECTION_TITLE">Registro</p>
                        <p class="mt-3 text-[0.75rem] text-slate-500 dark:text-brand-gray">
                            Registrada {{ dateTime(bond.created_at) }}<template v-if="bond.created_by"> por {{ bond.created_by }}</template>
                        </p>
                    </section>
                </div>
            </div>
        </div>
    </AppShell>
</template>
