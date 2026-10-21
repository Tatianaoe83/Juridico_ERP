<script setup>
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Building2,
    CalendarDays,
    CircleDot,
    Download,
    FileCheck,
    FileText,
    Globe,
    Hash,
    Landmark,
    MessageSquareText,
    Paperclip,
    ScanLine,
    ShieldCheck,
    Truck,
    User,
    Wallet,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppShell from '@/Layouts/AppShell.vue';
import { POLICY_COVERAGES, coverageStatus } from '@/lib/coverages';
import { UNIT_TYPES, beneficiaryDetail, fleetListUrl, money, periodLabel, shortDate, unitStatus } from '@/lib/units';

const props = defineProps({
    /** El vehículo, su póliza vigente (policy_detail) y sus documentos oficiales. */
    unit: { type: Object, required: true },
});

const policy = computed(() => props.unit.policy_detail);

/** Los datos de la póliza, en fichas como los de la unidad. */
const policyDetails = computed(() =>
    policy.value
        ? [
              // En el orden del formulario: el financiamiento y el beneficiario, luego la póliza.
              { label: 'Financiamiento', value: policy.value.financing ? 'Con financiamiento' : 'Sin financiamiento', icon: Wallet },
              beneficiaryDetail(policy.value),
              { label: 'Aseguradora', value: policy.value.insurer, icon: Landmark },
              { label: 'Número de póliza', value: policy.value.policy, icon: FileCheck },
              { label: 'Certificado', value: policy.value.certificate, icon: Hash },
              { label: 'Cobertura', value: POLICY_COVERAGES[policy.value.coverage] ?? policy.value.coverage, icon: ShieldCheck },
              { label: 'Endoso', value: policy.value.endorsement ? 'Con endoso USA/Canada' : 'Sin endoso USA/Canada', icon: Globe },
              // La del proveedor no tiene semestres: su vigencia va aquí.
              ...(policy.value.source === 'supplier'
                  ? [{ label: 'Vigencia', value: `${shortDate(policy.value.valid_from) ?? '—'} – ${shortDate(policy.value.valid_until) ?? '—'}`, icon: CalendarDays }]
                  : []),
          ]
        : [],
);

const SEMESTER_TITLES = { first: 'Primer semestre', second: 'Segundo semestre' };

const listUrl = fleetListUrl(props.unit.policy_detail?.source);

const breadcrumbs = [
    { label: 'Inicio', href: '/dashboard' },
    { label: 'Flotillas', href: listUrl },
    { label: `${props.unit.brand} ${props.unit.model}` },
];

const status = computed(() => unitStatus(props.unit.status));

/** Los datos sueltos, en fichas y en el mismo orden que el formulario. */
const details = computed(() => [
    { label: 'Tipo', value: UNIT_TYPES[props.unit.type]?.label ?? props.unit.type, icon: UNIT_TYPES[props.unit.type]?.icon ?? Truck },
    { label: 'Marca', value: props.unit.brand, icon: Truck },
    { label: 'Modelo', value: props.unit.model, icon: Truck },
    { label: 'Número de serie', value: props.unit.serial_number, icon: ScanLine },
    { label: 'Placa', value: props.unit.plate, icon: Hash },
    { label: '# Económico', value: props.unit.economic_number, icon: Hash },
    { label: 'Asignación', value: props.unit.responsible, icon: User },
    { label: 'Estado', value: status.value.label, icon: CircleDot },
    { label: 'Unidad de negocio', value: props.unit.business_unit, icon: Building2 },
]);

/** El enlace de descarga de un archivo de la unidad. */
function fileUrl(file) {
    return `/flotillas/${props.unit.id}/evidencias/${file.id}`;
}

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

const SECTION_TITLE = 'text-[0.62rem] font-bold text-slate-400 dark:text-brand-gray/80';
</script>

<template>
    <Head :title="`Unidad ${unit.brand} ${unit.model}`" />

    <AppShell :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-3 pb-6 tall:gap-4">
            <!-- Encabezado: qué unidad es y cómo está -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <span
                        class="grid size-9 shrink-0 place-content-center rounded-xl bg-gradient-to-br from-brand-light to-brand text-white shadow-md shadow-brand/25 ring-1 ring-white/10"
                    >
                        <Truck class="size-4" />
                    </span>
                    <div class="min-w-0">
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
                        :href="listUrl"
                        class="inline-flex h-9 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-[0.8rem] font-semibold text-slate-700 transition-colors duration-150 hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-500/10 dark:border-white/10 dark:bg-transparent dark:text-brand-gray dark:hover:bg-white/[0.06] dark:hover:text-white"
                    >
                        <ArrowLeft class="size-4" />
                        Volver
                    </Link>
                </div>
            </div>

            <!-- Datos de la unidad -->
            <section :class="CARD">
                <p :class="SECTION_TITLE">Datos de la unidad</p>

                <dl class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="detail in details" :key="detail.label" class="flex items-start gap-2.5">
                        <span
                            class="mt-0.5 grid size-7 shrink-0 place-content-center rounded-lg bg-slate-50 text-slate-400 ring-1 ring-inset ring-slate-500/10 dark:bg-white/[0.05] dark:text-brand-gray dark:ring-white/10"
                        >
                            <component :is="detail.icon" class="size-3.5" />
                        </span>
                        <div class="min-w-0">
                            <dt class="text-[0.6rem] font-bold text-slate-400 dark:text-brand-gray/70">{{ detail.label }}</dt>
                            <dd class="truncate text-[0.82rem] font-semibold text-slate-800 dark:text-white" :title="detail.value ?? undefined">
                                {{ detail.value ?? '—' }}
                            </dd>
                        </div>
                    </div>
                </dl>
            </section>

            <!-- Póliza vigente: datos, vigencia por semestre y costo anual -->
            <section :class="CARD">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p :class="SECTION_TITLE">Póliza</p>
                    <span
                        v-if="policy"
                        class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[0.7rem] font-bold whitespace-nowrap ring-1 ring-inset"
                        :class="coverageStatus(policy.status).tone"
                    >
                        <component :is="coverageStatus(policy.status).icon" class="size-3" />
                        {{ coverageStatus(policy.status).label }}
                    </span>
                </div>

                <template v-if="policy">
                    <dl class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div v-for="detail in policyDetails" :key="detail.label" class="flex items-start gap-2.5">
                            <span
                                class="mt-0.5 grid size-7 shrink-0 place-content-center rounded-lg bg-slate-50 text-slate-400 ring-1 ring-inset ring-slate-500/10 dark:bg-white/[0.05] dark:text-brand-gray dark:ring-white/10"
                            >
                                <component :is="detail.icon" class="size-3.5" />
                            </span>
                            <div class="min-w-0">
                                <dt class="text-[0.6rem] font-bold text-slate-400 dark:text-brand-gray/70">{{ detail.label }}</dt>
                                <dd class="truncate text-[0.82rem] font-semibold text-slate-800 dark:text-white" :title="detail.value ?? undefined">
                                    {{ detail.value ?? '—' }}
                                </dd>
                            </div>
                        </div>
                    </dl>

                    <!-- La del proveedor no tiene semestres: su vigencia va en las fichas y su pago es único y anual -->
                    <div v-if="policy.source === 'supplier'" class="mt-4 grid gap-3 lg:grid-cols-3">
                        <article class="rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2.5 dark:border-white/10 dark:bg-white/[0.02]">
                            <p class="text-[0.8rem] font-bold text-slate-800 dark:text-white">Pago único anual</p>
                            <p class="mt-1.5 text-[0.9rem] font-bold tabular-nums text-slate-800 dark:text-white">
                                {{ policy.payment?.amount != null ? money(policy.payment.amount) : '—' }}
                            </p>
                        </article>

                        <div class="flex flex-col justify-center rounded-xl bg-slate-50/70 px-3.5 py-2 ring-1 ring-inset ring-slate-200/70 lg:col-span-2 dark:bg-white/[0.03] dark:ring-white/[0.06]">
                            <p class="text-[0.62rem] font-bold text-slate-400 dark:text-brand-gray/80">Costo anual (IVA incluido)</p>
                            <p class="text-lg leading-tight font-bold tabular-nums text-brand dark:text-white">{{ money(policy.annual_cost) }}</p>
                            <p class="text-[0.7rem] tabular-nums text-slate-500 dark:text-brand-gray">
                                IVA {{ Math.round(policy.tax_rate * 100) }}%: {{ money(policy.tax) }} · Subtotal: {{ money(policy.subtotal) }}
                            </p>
                        </div>
                    </div>

                    <div v-else class="mt-4 grid gap-3 lg:grid-cols-3">
                        <article
                            v-for="semester in policy.semesters"
                            :key="semester.payment"
                            class="rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2.5 dark:border-white/10 dark:bg-white/[0.02]"
                        >
                            <p class="text-[0.8rem] font-bold text-slate-800 dark:text-white">{{ SEMESTER_TITLES[semester.payment] }}</p>
                            <p class="text-[0.7rem] tabular-nums text-slate-500 dark:text-brand-gray">{{ periodLabel(semester) }}</p>
                            <p class="mt-1.5 text-[0.9rem] font-bold tabular-nums text-slate-800 dark:text-white">
                                {{ semester.amount !== null ? money(semester.amount) : '—' }}
                            </p>
                        </article>

                        <div class="flex flex-col justify-center rounded-xl bg-slate-50/70 px-3.5 py-2 ring-1 ring-inset ring-slate-200/70 dark:bg-white/[0.03] dark:ring-white/[0.06]">
                            <p class="text-[0.62rem] font-bold text-slate-400 dark:text-brand-gray/80">Costo anual (IVA incluido)</p>
                            <p class="text-lg leading-tight font-bold tabular-nums text-brand dark:text-white">{{ money(policy.annual_cost) }}</p>
                            <p class="text-[0.7rem] tabular-nums text-slate-500 dark:text-brand-gray">
                                IVA {{ Math.round(policy.tax_rate * 100) }}%: {{ money(policy.tax) }} · Subtotal: {{ money(policy.subtotal) }}
                            </p>
                        </div>
                    </div>
                </template>

                <p v-else class="mt-3 flex items-center gap-2 text-[0.78rem] text-slate-400 dark:text-brand-gray/70">
                    <FileCheck class="size-4" />
                    Sin póliza registrada. Edita la unidad para capturarla.
                </p>
            </section>

            <div class="grid gap-3 tall:gap-4 lg:grid-cols-2">
                <!-- Documentos de la unidad: se descargan con el nombre con el que se subieron -->
                <section :class="CARD">
                    <p :class="SECTION_TITLE">Documentos oficiales</p>

                    <ul v-if="unit.documents.length" class="mt-3 flex flex-col gap-1.5">
                        <li
                            v-for="evidence in unit.documents"
                            :key="evidence.id"
                            class="flex items-center gap-2.5 rounded-lg bg-slate-50 px-2.5 py-2 text-[0.78rem] dark:bg-white/[0.04]"
                        >
                            <FileText class="size-4 shrink-0 text-slate-400 dark:text-brand-gray" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-semibold text-slate-700 dark:text-slate-200" :title="evidence.name">{{ evidence.name }}</span>
                                <span class="block text-[0.68rem] text-slate-400 dark:text-brand-gray/70">
                                    {{ fileSize(evidence.size) }}
                                    <template v-if="evidence.uploaded_by"> · {{ evidence.uploaded_by }}</template>
                                    · {{ dateTime(evidence.created_at) }}
                                </span>
                            </span>
                            <a
                                :href="fileUrl(evidence)"
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
                        Sin documentos cargados.
                    </p>
                </section>

                <!-- Comentarios -->
                <section :class="CARD">
                    <p :class="SECTION_TITLE">Comentarios</p>

                    <p v-if="unit.comments" class="mt-3 text-[0.8rem] leading-relaxed whitespace-pre-line text-slate-700 dark:text-slate-200">
                        {{ unit.comments }}
                    </p>
                    <p v-else class="mt-3 flex items-center gap-2 text-[0.78rem] text-slate-400 dark:text-brand-gray/70">
                        <MessageSquareText class="size-4" />
                        Sin comentarios.
                    </p>
                </section>
            </div>
        </div>
    </AppShell>
</template>
