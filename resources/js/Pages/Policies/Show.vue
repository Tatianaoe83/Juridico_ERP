<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Ban,
    Building2,
    CalendarClock,
    CalendarRange,
    FileCheck,
    FilePen,
    Hash,
    Landmark,
    Loader2,
    MessageSquareText,
    Paperclip,
    ScanLine,
    Truck,
    Upload,
    User,
    Wallet,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppShell from '@/Layouts/AppShell.vue';
import ConfirmDeleteDialog from '@/components/app/ConfirmDeleteDialog.vue';
import FilePreviewDialog from '@/components/app/FilePreviewDialog.vue';
import PolicyPaymentDialog from '@/components/app/PolicyPaymentDialog.vue';
import { usePermissions } from '@/composables/usePermissions';
import { COVERAGE_TYPES, coverageStatus } from '@/lib/coverages';
import { FILE_KINDS, fileSize, kindOf } from '@/lib/files';
import { money, shortDate } from '@/lib/units';

const props = defineProps({
    /** El periodo completo: póliza, unidad, cuotas con comprobantes, costo y cancelación. */
    policy: { type: Object, required: true },
});

const breadcrumbs = [
    { label: 'Inicio', href: '/calendario' },
    { label: 'Pólizas y Fianzas', href: '/polizas' },
    { label: `Póliza ${props.policy.policy}` },
];

// La unidad lleva a su ficha solo si puede ver flotillas.
const { can } = usePermissions();

const status = computed(() => coverageStatus(props.policy.status));

const unit = computed(() => props.policy.unit);

const unitName = computed(() => (unit.value ? `${unit.value.brand} ${unit.value.model}` : null));

/** «15 ene 2026 – 15 ene 2027»; con una sola fecha, esa. */
function range(from, to) {
    const start = shortDate(from);
    const end = shortDate(to);

    if (!start && !end) return null;

    return start && end ? `${start} – ${end}` : (start ?? end);
}

/** Los datos de la póliza, en fichas. */
const details = computed(() => [
    { label: 'Póliza', value: props.policy.policy, icon: FileCheck },
    { label: 'Certificado', value: props.policy.certificate, icon: Hash },
    { label: 'Aseguradora', value: props.policy.insurer, icon: Landmark },
    { label: 'Endoso', value: props.policy.endorsement ? 'Sí' : 'No', icon: FilePen },
    { label: 'Vigencia', value: range(props.policy.valid_from, props.policy.valid_until), icon: CalendarRange },
]);

/** La unidad asegurada, con lo que pide la póliza: descripción, serie, placas, económico y asignación. */
const unitDetails = computed(() =>
    unit.value
        ? [
              { label: 'Descripción', value: unitName.value, icon: Truck },
              { label: 'Número de serie', value: unit.value.serial_number, icon: ScanLine },
              { label: 'Placa', value: unit.value.plate, icon: Hash },
              { label: '# Económico', value: unit.value.economic_number, icon: Hash },
              { label: 'Asignación', value: unit.value.business_unit, icon: Building2 },
              { label: 'Responsable', value: unit.value.responsible, icon: User },
          ]
        : [],
);

/* ---------- Cuotas ---------- */

const PAYMENT_TITLE = { first: 'Primer pago', second: 'Segundo pago' };

/** Cómo va cada cuota según su fecha límite de pago. */
const INSTALLMENT_STATUS = {
    paid: { label: 'Pagada', tone: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-400/10 dark:text-emerald-300 dark:ring-emerald-400/25' },
    pending: { label: 'Pendiente', tone: 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/25' },
    overdue: { label: 'Vencida', tone: 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-400/10 dark:text-red-300 dark:ring-red-400/25' },
    cancelled: { label: 'Cancelada', tone: 'bg-slate-50 text-slate-600 ring-slate-500/15 dark:bg-white/[0.05] dark:text-brand-gray dark:ring-white/10' },
};

const cancellation = computed(() => props.policy.cancellation_requested_on || props.policy.cancelled_on);

/* ---------- Comprobantes y facturas ---------- */

const canUpdate = computed(() => can('polizas.update'));

const previewOpen = ref(false);
const previewFile = ref(null);

function openFile(file) {
    previewFile.value = { name: file.name, size: file.size ?? 0, source: `/polizas/${props.policy.id}/archivos/${file.id}` };
    previewOpen.value = true;
}

const fileKind = (name) => FILE_KINDS[kindOf(name)] ?? FILE_KINDS.pdf;

/** Se registra el pago mientras la cuota no esté pagada ni la póliza cancelada. */
const payable = (installment) => canUpdate.value && ['pending', 'overdue'].includes(installment.status);

// Registrar pago: un modal con la fecha y el comprobante.
const paymentOpen = ref(false);
const paying = ref(null);

function pay(installment) {
    paying.value = installment;
    paymentOpen.value = true;
}

// Facturas: se suben directo al elegirlas, sin modal.
const invoiceInput = ref(null);
const invoiceFor = ref(null);
const uploading = ref(null);
const invoiceErrors = ref({});

function pickInvoices(installment) {
    invoiceFor.value = installment.payment;
    invoiceInput.value?.click();
}

function uploadInvoices(event) {
    const files = Array.from(event.target.files);
    const payment = invoiceFor.value;

    event.target.value = '';

    if (!files.length || !payment) return;

    uploading.value = payment;
    invoiceErrors.value = {};

    router.post(
        `/polizas/${props.policy.id}/facturas/${payment}`,
        { invoices: files },
        {
            preserveScroll: true,
            forceFormData: true,
            onError: (errors) => (invoiceErrors.value = { [payment]: Object.values(errors) }),
            onFinish: () => (uploading.value = null),
        },
    );
}

// Quitar factura: con confirmación, como todo lo que borra.
const deleteOpen = ref(false);
const toDelete = ref(null);
const deleting = ref(null);

function askDelete(file) {
    toDelete.value = file;
    deleteOpen.value = true;
}

function destroyInvoice() {
    const file = toDelete.value;

    deleting.value = file.id;

    router.delete(`/polizas/${props.policy.id}/facturas/${file.id}`, {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
}

/** Los dos apartados de cada cuota, en el orden en que se piden. */
function fileSections(installment) {
    return [
        {
            key: 'receipts',
            title: 'Comprobante de pago',
            files: installment.receipts,
            empty: 'Sin comprobante.',
            removable: false,
            action: payable(installment) ? { label: 'Registrar pago', icon: Wallet, run: () => pay(installment) } : null,
        },
        {
            key: 'invoices',
            title: 'Facturas',
            files: installment.invoices,
            empty: 'Sin factura.',
            removable: canUpdate.value,
            action: canUpdate.value ? { label: 'Subir factura', icon: Upload, run: () => pickInvoices(installment) } : null,
        },
    ];
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

const TILE_ICON =
    'mt-0.5 grid size-7 shrink-0 place-content-center rounded-lg bg-slate-50 text-slate-400 ring-1 ring-inset ring-slate-500/10 dark:bg-white/[0.05] dark:text-brand-gray dark:ring-white/10';

const DT = 'text-[0.6rem] font-bold uppercase tracking-[0.12em] text-slate-400 dark:text-brand-gray/70';

const DD = 'truncate text-[0.82rem] font-semibold text-slate-800 dark:text-white';

const EMPTY = 'mt-3 flex items-center gap-2 text-[0.78rem] text-slate-400 dark:text-brand-gray/70';
</script>

<template>
    <Head :title="`Póliza ${policy.policy}`" />

    <AppShell :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-3 pb-6 tall:gap-4">
            <!-- Encabezado: qué póliza es y cómo está -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex min-w-0 flex-wrap items-center gap-3">
                    <span
                        class="grid size-9 shrink-0 place-content-center rounded-xl bg-gradient-to-br from-brand-light to-brand text-white shadow-md shadow-brand/25 ring-1 ring-white/10"
                    >
                        <FileCheck class="size-4" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-[0.6rem] font-bold uppercase tracking-[0.2em] text-slate-400 dark:text-brand-gray/80">{{ COVERAGE_TYPES.policy.label }}</p>
                        <h1 class="truncate text-xl font-bold tracking-tight text-brand dark:text-white">{{ policy.policy }}</h1>
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
                    <!-- Datos de la póliza -->
                    <section :class="CARD">
                        <p :class="SECTION_TITLE">Datos de la póliza</p>

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

                    <!-- Unidad asegurada -->
                    <section :class="CARD">
                        <div class="flex items-center justify-between gap-3">
                            <p :class="SECTION_TITLE">Unidad asegurada</p>
                            <Link
                                v-if="unit && can('flotillas.view')"
                                :href="`/flotillas/${unit.id}`"
                                class="text-[0.72rem] font-semibold text-brand hover:underline dark:text-white"
                            >
                                Ver unidad
                            </Link>
                        </div>

                        <dl v-if="unit" class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            <div v-for="detail in unitDetails" :key="detail.label" class="flex items-start gap-2.5">
                                <span :class="TILE_ICON"><component :is="detail.icon" class="size-3.5" /></span>
                                <div class="min-w-0">
                                    <dt :class="DT">{{ detail.label }}</dt>
                                    <dd :class="DD" :title="detail.value ?? undefined">{{ detail.value ?? '—' }}</dd>
                                </div>
                            </div>
                        </dl>
                        <p v-else :class="EMPTY">
                            <Truck class="size-4" />
                            La unidad ya no existe.
                        </p>
                    </section>

                    <!-- Cuotas: los dos pagos semestrales con sus comprobantes -->
                    <section :class="CARD">
                        <p :class="SECTION_TITLE">Cuotas</p>

                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <article
                                v-for="installment in policy.payments"
                                :key="installment.payment"
                                class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50/60 p-3.5 dark:border-white/10 dark:bg-white/[0.03]"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-[0.8rem] font-bold text-slate-800 dark:text-white">{{ PAYMENT_TITLE[installment.payment] }}</p>
                                    <span
                                        class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[0.65rem] font-bold ring-1 ring-inset"
                                        :class="INSTALLMENT_STATUS[installment.status].tone"
                                    >
                                        {{ INSTALLMENT_STATUS[installment.status].label }}
                                    </span>
                                </div>

                                <p class="text-xl leading-none font-bold tabular-nums text-slate-800 dark:text-white">
                                    {{ installment.amount !== null ? money(installment.amount) : '—' }}
                                </p>

                                <dl class="grid grid-cols-2 gap-x-3 gap-y-2 text-[0.75rem]">
                                    <div class="col-span-2">
                                        <dt :class="DT">Periodo</dt>
                                        <dd class="font-semibold tabular-nums text-slate-700 dark:text-slate-200">{{ range(installment.starts_on, installment.ends_on) ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt :class="DT">Fecha límite</dt>
                                        <dd class="flex items-center gap-1 font-semibold tabular-nums text-slate-700 dark:text-slate-200">
                                            <CalendarClock class="size-3.5 text-slate-400 dark:text-brand-gray" />
                                            {{ shortDate(installment.due_on) ?? 'Sin fecha' }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt :class="DT">Pagado el</dt>
                                        <dd class="font-semibold tabular-nums text-slate-700 dark:text-slate-200">{{ shortDate(installment.paid_at) ?? '—' }}</dd>
                                    </div>
                                </dl>

                                <!-- Comprobante de pago y facturas: clic en el archivo para verlo -->
                                <div
                                    v-for="section in fileSections(installment)"
                                    :key="section.key"
                                    class="border-t border-slate-200/70 pt-3 dark:border-white/[0.06]"
                                >
                                    <div class="flex items-center justify-between gap-2">
                                        <p :class="DT">{{ section.title }}</p>
                                        <button
                                            v-if="section.action"
                                            type="button"
                                            class="inline-flex h-7 cursor-pointer items-center gap-1.5 rounded-lg px-2 text-[0.7rem] font-semibold text-brand transition-colors hover:bg-brand/[0.07] focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-brand/25 disabled:pointer-events-none disabled:opacity-50 dark:text-white dark:hover:bg-white/10"
                                            :disabled="section.key === 'invoices' && uploading === installment.payment"
                                            @click="section.action.run"
                                        >
                                            <Loader2 v-if="section.key === 'invoices' && uploading === installment.payment" class="size-3.5 animate-spin" />
                                            <component :is="section.action.icon" v-else class="size-3.5" />
                                            {{ section.action.label }}
                                        </button>
                                    </div>

                                    <ul v-if="section.files.length" class="mt-1.5 flex flex-col gap-1.5">
                                        <li
                                            v-for="file in section.files"
                                            :key="file.id"
                                            class="relative"
                                            :class="deleting === file.id && 'pointer-events-none opacity-40'"
                                        >
                                            <button
                                                type="button"
                                                class="flex w-full cursor-pointer items-center gap-2.5 rounded-lg bg-white px-2.5 py-2 text-left text-[0.75rem] ring-1 ring-slate-200 transition-colors hover:ring-brand/30 focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-brand/25 dark:bg-white/[0.04] dark:ring-white/10 dark:hover:ring-white/25"
                                                :class="section.removable && 'pr-9'"
                                                @click="openFile(file)"
                                            >
                                                <span class="grid size-7 shrink-0 place-content-center rounded-md" :class="fileKind(file.name).tile">
                                                    <component :is="fileKind(file.name).icon" class="size-3.5" />
                                                </span>
                                                <span class="min-w-0 flex-1">
                                                    <span class="block truncate font-semibold text-slate-700 dark:text-slate-200" :title="file.name">{{ file.name }}</span>
                                                    <span class="block text-[0.66rem] text-slate-400 dark:text-brand-gray/70">
                                                        {{ fileSize(file.size ?? 0) }}<template v-if="file.uploaded_by"> · {{ file.uploaded_by }}</template>
                                                    </span>
                                                </span>
                                            </button>
                                            <button
                                                v-if="section.removable"
                                                type="button"
                                                class="absolute top-1/2 right-1.5 grid size-6 -translate-y-1/2 cursor-pointer place-content-center rounded-md text-slate-400 transition-colors hover:bg-red-50 hover:text-red-600 focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-red-500/25 dark:hover:bg-red-500/10 dark:hover:text-red-300"
                                                :aria-label="`Quitar ${file.name}`"
                                                title="Quitar factura"
                                                @click="askDelete(file)"
                                            >
                                                <X class="size-3.5" />
                                            </button>
                                        </li>
                                    </ul>
                                    <p v-else class="mt-1 flex items-center gap-1.5 text-[0.72rem] text-slate-400 dark:text-brand-gray/70">
                                        <Paperclip class="size-3.5" />
                                        {{ section.empty }}
                                    </p>

                                    <template v-if="section.key === 'invoices'">
                                        <p v-for="error in invoiceErrors[installment.payment] ?? []" :key="error" class="mt-1 text-[0.7rem] font-medium text-red-600 dark:text-red-400">
                                            {{ error }}
                                        </p>
                                    </template>
                                </div>
                            </article>
                        </div>
                    </section>
                </div>

                <!-- Columna lateral -->
                <div class="flex flex-col gap-3 tall:gap-4">
                    <!-- Costo anual desglosado -->
                    <section :class="CARD">
                        <p :class="SECTION_TITLE">Costo anual</p>

                        <p class="mt-3 text-2xl font-bold tabular-nums text-brand dark:text-white">{{ money(policy.annual_cost) }}</p>
                        <p class="text-[0.7rem] text-slate-400 dark:text-brand-gray/70">IVA incluido · suma de las dos cuotas</p>

                        <dl class="mt-3 flex flex-col gap-1.5 border-t border-slate-100 pt-3 text-[0.78rem] dark:border-white/[0.06]">
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500 dark:text-brand-gray">Subtotal</dt>
                                <dd class="font-semibold tabular-nums text-slate-700 dark:text-slate-200">{{ money(policy.subtotal) }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500 dark:text-brand-gray">IVA ({{ Math.round(policy.tax_rate * 100) }}%)</dt>
                                <dd class="font-semibold tabular-nums text-slate-700 dark:text-slate-200">{{ money(policy.tax) }}</dd>
                            </div>
                        </dl>
                    </section>

                    <!-- Cancelación: solo si se pidió -->
                    <section v-if="cancellation" :class="CARD">
                        <p :class="SECTION_TITLE">Cancelación</p>

                        <dl class="mt-3 flex flex-col gap-3">
                            <div class="flex items-start gap-2.5">
                                <span :class="TILE_ICON"><CalendarClock class="size-3.5" /></span>
                                <div class="min-w-0">
                                    <dt :class="DT">Solicitud de cancelación</dt>
                                    <dd :class="DD">{{ shortDate(policy.cancellation_requested_on) ?? '—' }}</dd>
                                </div>
                            </div>
                            <div class="flex items-start gap-2.5">
                                <span :class="TILE_ICON"><Ban class="size-3.5" /></span>
                                <div class="min-w-0">
                                    <dt :class="DT">Fecha de cancelación</dt>
                                    <dd :class="DD">{{ shortDate(policy.cancelled_on) ?? 'Pendiente' }}</dd>
                                </div>
                            </div>
                        </dl>
                    </section>

                    <!-- Comentarios -->
                    <section :class="CARD">
                        <p :class="SECTION_TITLE">Comentarios</p>

                        <p v-if="policy.comments" class="mt-3 text-[0.8rem] leading-relaxed whitespace-pre-line text-slate-700 dark:text-slate-200">
                            {{ policy.comments }}
                        </p>
                        <p v-else :class="EMPTY">
                            <MessageSquareText class="size-4" />
                            Sin comentarios.
                        </p>
                    </section>

                    <section :class="CARD">
                        <p :class="SECTION_TITLE">Registro</p>
                        <p class="mt-3 text-[0.75rem] text-slate-500 dark:text-brand-gray">
                            Registrada {{ dateTime(policy.created_at) }}<template v-if="policy.created_by"> por {{ policy.created_by }}</template>
                        </p>
                    </section>
                </div>
            </div>
        </div>

        <FilePreviewDialog v-model:open="previewOpen" :file="previewFile" />

        <PolicyPaymentDialog v-model:open="paymentOpen" :policy="policy" :installment="paying" />

        <ConfirmDeleteDialog
            v-model:open="deleteOpen"
            :text="toDelete ? `¿Seguro que quieres quitar la factura «${toDelete.name}»?` : ''"
            @confirm="destroyInvoice"
        />

        <!-- Uno solo para las dos cuotas: `invoiceFor` dice a cuál van -->
        <input ref="invoiceInput" type="file" multiple accept=".pdf,.xml" class="hidden" @change="uploadInvoices" />
    </AppShell>
</template>
