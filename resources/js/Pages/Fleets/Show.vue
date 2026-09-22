<script setup>
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BadgeCheck,
    Building2,
    CalendarClock,
    Download,
    FileText,
    Hash,
    MessageSquareText,
    Paperclip,
    Pencil,
    ScanLine,
    Truck,
    User,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppShell from '@/Layouts/AppShell.vue';
import { usePermissions } from '@/composables/usePermissions';
import { money, paymentCountdown, periodLabel, unitStatus } from '@/lib/units';

const props = defineProps({
    /** La unidad ya calculada: importes, periodos y evidencias. */
    unit: { type: Object, required: true },
});

const breadcrumbs = [
    { label: 'Inicio', href: '/calendario' },
    { label: 'Flotillas', href: '/flotillas' },
    { label: props.unit.policy },
];

const { can } = usePermissions();

const status = computed(() => unitStatus(props.unit.status));

/** Los datos sueltos, en fichas: cada uno con su icono y su etiqueta. */
const details = computed(() => [
    { label: 'Póliza', value: props.unit.policy, icon: BadgeCheck },
    { label: 'Certificado', value: props.unit.certificate, icon: FileText },
    { label: 'Unidad de negocio', value: props.unit.business_unit, icon: Building2 },
    { label: 'Número de serie', value: props.unit.serial_number, icon: ScanLine },
    { label: 'Placa', value: props.unit.plate, icon: Hash },
    { label: '# Económico', value: props.unit.economic_number, icon: Hash },
    { label: 'Responsable', value: props.unit.responsible, icon: User },
    {
        label: 'Endoso',
        value: props.unit.usa_canada_endorsement ? 'Cobertura USA / Canadá' : 'Sin endoso',
        icon: BadgeCheck,
    },
]);

/** Los dos semestres, con su periodo, su importe y cómo van. */
const payments = computed(() =>
    [
        { number: 1, payment: props.unit.first_payment },
        { number: 2, payment: props.unit.second_payment },
    ].map(({ number, payment }) => ({
        number,
        period: periodLabel(payment),
        amount: payment?.amount ?? 0,
        countdown: paymentCountdown(payment?.ends_on),
    })),
);

/** «1.4 MB», «812 KB». */
function fileSize(bytes) {
    if (!bytes) return '—';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

/** «22 sep 2026, 14:30» para las marcas de tiempo del registro. */
function dateTime(iso) {
    if (!iso) return '—';

    return new Date(iso).toLocaleString('es-MX', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

const CARD =
    'rounded-2xl border border-slate-200/80 bg-white p-4 shadow-[0_1px_3px_rgb(2_29_73/0.04),0_8px_24px_-12px_rgb(2_29_73/0.08)] ' +
    'tall:p-5 dark:border-white/[0.08] dark:bg-brand-deep dark:shadow-none';

const SECTION_TITLE = 'text-[0.62rem] font-bold uppercase tracking-[0.14em] text-slate-400 dark:text-brand-gray/80';
</script>

<template>
    <Head :title="`Unidad ${unit.policy}`" />

    <AppShell :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-3 pb-6 tall:gap-4">
            <!-- Encabezado: qué unidad es, cómo está y qué se puede hacer con ella -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <span
                        class="grid size-9 shrink-0 place-content-center rounded-xl bg-gradient-to-br from-brand-light to-brand text-white shadow-md shadow-brand/25 ring-1 ring-white/10"
                    >
                        <Truck class="size-4" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-[0.6rem] font-bold uppercase tracking-[0.2em] text-slate-400 dark:text-brand-gray/80">Flotillas</p>
                        <h1 class="truncate text-xl font-bold tracking-tight text-brand dark:text-white">{{ unit.brand }} {{ unit.model }}</h1>
                    </div>
                    <span
                        class="inline-flex shrink-0 items-center rounded-md px-1.5 py-0.5 text-[0.7rem] font-bold whitespace-nowrap ring-1 ring-inset"
                        :class="status.tone"
                    >
                        {{ status.label }}
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    <Link
                        href="/flotillas"
                        class="inline-flex h-9 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-[0.8rem] font-semibold text-slate-700 transition-colors duration-150 hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-500/10 dark:border-white/10 dark:bg-transparent dark:text-brand-gray dark:hover:bg-white/[0.06] dark:hover:text-white"
                    >
                        <ArrowLeft class="size-4" />
                        Volver
                    </Link>
                    <Link
                        v-if="can('flotillas.update')"
                        :href="`/flotillas/${unit.id}/editar`"
                        class="inline-flex h-9 items-center gap-2 rounded-xl bg-brand px-4 text-[0.8rem] font-semibold text-white shadow-md shadow-brand/25 transition-all duration-150 hover:bg-brand/90 hover:shadow-lg hover:shadow-brand/30 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/25 active:translate-y-px dark:bg-brand-light dark:shadow-black/30 dark:hover:bg-brand-light/90"
                    >
                        <Pencil class="size-4" />
                        Editar
                    </Link>
                </div>
            </div>

            <!-- Datos de la unidad -->
            <section :class="CARD">
                <p :class="SECTION_TITLE">Datos de la unidad</p>

                <dl class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="detail in details" :key="detail.label" class="flex items-start gap-2.5">
                        <span
                            class="mt-0.5 grid size-7 shrink-0 place-content-center rounded-lg bg-slate-50 text-slate-400 ring-1 ring-inset ring-slate-500/10 dark:bg-white/[0.05] dark:text-brand-gray dark:ring-white/10"
                        >
                            <component :is="detail.icon" class="size-3.5" />
                        </span>
                        <div class="min-w-0">
                            <dt class="text-[0.6rem] font-bold uppercase tracking-[0.12em] text-slate-400 dark:text-brand-gray/70">{{ detail.label }}</dt>
                            <dd class="truncate text-[0.82rem] font-semibold text-slate-800 dark:text-white" :title="detail.value ?? undefined">
                                {{ detail.value ?? '—' }}
                            </dd>
                        </div>
                    </div>
                </dl>
            </section>

            <!-- Pagos semestrales: el periodo, lo que cuesta y cuánto falta -->
            <section :class="CARD">
                <p :class="SECTION_TITLE">Pagos semestrales</p>

                <div class="mt-3 grid gap-3 lg:grid-cols-2">
                    <div
                        v-for="item in payments"
                        :key="item.number"
                        class="rounded-xl bg-slate-50/70 p-3 ring-1 ring-inset ring-slate-200/70 dark:bg-white/[0.03] dark:ring-white/[0.06]"
                    >
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <span
                                class="grid size-5 shrink-0 place-content-center rounded-md bg-brand/[0.08] text-[0.6rem] font-bold text-brand dark:bg-white/10 dark:text-white"
                            >
                                {{ item.number }}
                            </span>
                            <p class="text-[0.75rem] font-bold text-slate-700 dark:text-slate-200">
                                {{ item.number === 1 ? 'Primer pago' : 'Segundo pago' }}
                            </p>
                            <span
                                v-if="item.countdown"
                                class="ml-auto inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[0.68rem] font-bold whitespace-nowrap ring-1 ring-inset"
                                :class="item.countdown.tone"
                            >
                                <CalendarClock class="size-3" />
                                {{ item.countdown.label }}
                            </span>
                        </div>

                        <p class="mt-2 text-[0.75rem] text-slate-500 dark:text-brand-gray">{{ item.period }}</p>
                        <p class="text-lg font-bold text-slate-800 tabular-nums dark:text-white">{{ money(item.amount) }}</p>
                    </div>
                </div>

                <!-- Los importes se capturan con IVA: aquí solo se desglosa -->
                <dl class="mt-3 grid gap-2 sm:grid-cols-3">
                    <div class="rounded-xl bg-slate-50/70 px-3 py-2 dark:bg-white/[0.03]">
                        <dt class="text-[0.62rem] font-bold uppercase tracking-[0.14em] text-slate-400 dark:text-brand-gray/80">Subtotal</dt>
                        <dd class="text-sm font-bold text-slate-800 tabular-nums dark:text-white">{{ money(unit.subtotal) }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50/70 px-3 py-2 dark:bg-white/[0.03]">
                        <dt class="text-[0.62rem] font-bold uppercase tracking-[0.14em] text-slate-400 dark:text-brand-gray/80">IVA incluido (16%)</dt>
                        <dd class="text-sm font-bold text-slate-800 tabular-nums dark:text-white">{{ money(unit.tax) }}</dd>
                    </div>
                    <div class="rounded-xl bg-brand/[0.07] px-3 py-2 dark:bg-white/10">
                        <dt class="text-[0.62rem] font-bold uppercase tracking-[0.14em] text-brand/70 dark:text-brand-gray/80">Costo anual</dt>
                        <dd class="text-sm font-bold text-brand tabular-nums dark:text-white">{{ money(unit.annual_cost) }}</dd>
                    </div>
                </dl>
            </section>

            <div class="grid gap-3 tall:gap-4 lg:grid-cols-2">
                <!-- Evidencias: se descargan con el nombre con el que se subieron -->
                <section :class="CARD">
                    <p :class="SECTION_TITLE">Evidencias</p>

                    <ul v-if="unit.evidences.length" class="mt-3 flex flex-col gap-1.5">
                        <li
                            v-for="evidence in unit.evidences"
                            :key="evidence.id"
                            class="flex items-center gap-2.5 rounded-lg bg-slate-50 px-2.5 py-2 text-[0.78rem] dark:bg-white/[0.04]"
                        >
                            <FileText class="size-4 shrink-0 text-slate-400 dark:text-brand-gray" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-semibold text-slate-700 dark:text-slate-200" :title="evidence.name">{{ evidence.name }}</span>
                                <span class="block text-[0.68rem] text-slate-400 dark:text-brand-gray/70">
                                    {{ fileSize(evidence.size) }}
                                    <template v-if="evidence.uploaded_by"> · {{ evidence.uploaded_by }}</template>
                                </span>
                            </span>
                            <a
                                :href="`/flotillas/${unit.id}/evidencias/${evidence.id}`"
                                class="grid size-7 shrink-0 place-content-center rounded-lg text-slate-500 transition-colors duration-150 hover:bg-brand/[0.07] hover:text-brand focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-brand/25 dark:text-brand-gray dark:hover:bg-white/10 dark:hover:text-white"
                                :aria-label="`Descargar ${evidence.name}`"
                                title="Descargar"
                            >
                                <Download class="size-4" />
                            </a>
                        </li>
                    </ul>

                    <p v-else class="mt-3 flex items-center gap-2 text-[0.78rem] text-slate-400 dark:text-brand-gray/70">
                        <Paperclip class="size-4" />
                        Sin evidencias cargadas.
                    </p>
                </section>

                <!-- Comentarios y de dónde salió el registro -->
                <section :class="CARD">
                    <p :class="SECTION_TITLE">Comentarios / financiamiento</p>

                    <p v-if="unit.comments" class="mt-3 text-[0.8rem] leading-relaxed whitespace-pre-line text-slate-700 dark:text-slate-200">
                        {{ unit.comments }}
                    </p>
                    <p v-else class="mt-3 flex items-center gap-2 text-[0.78rem] text-slate-400 dark:text-brand-gray/70">
                        <MessageSquareText class="size-4" />
                        Sin comentarios.
                    </p>

                    <dl class="mt-4 grid gap-2 border-t border-slate-100 pt-3 text-[0.72rem] sm:grid-cols-2 dark:border-white/[0.06]">
                        <div>
                            <dt class="text-[0.6rem] font-bold uppercase tracking-[0.12em] text-slate-400 dark:text-brand-gray/70">Registrado por</dt>
                            <dd class="text-slate-700 dark:text-slate-200">{{ unit.created_by ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[0.6rem] font-bold uppercase tracking-[0.12em] text-slate-400 dark:text-brand-gray/70">Alta</dt>
                            <dd class="text-slate-700 dark:text-slate-200">{{ dateTime(unit.created_at) }}</dd>
                        </div>
                    </dl>
                </section>
            </div>
        </div>
    </AppShell>
</template>
