<script setup>
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Building2,
    CalendarDays,
    CircleDollarSign,
    Download,
    FileCheck,
    Hash,
    Landmark,
    MessageSquareText,
    Paperclip,
    Pencil,
    ShieldCheck,
    Tractor,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppShell from '@/Layouts/AppShell.vue';
import AppModal from '@/components/app/AppModal.vue';
import FilePreviewDialog from '@/components/app/FilePreviewDialog.vue';
import { usePermissions } from '@/composables/usePermissions';
import { coverageStatus } from '@/lib/coverages';
import { FILE_KINDS, extensionOf, fileSize, kindOf } from '@/lib/files';
import { MACHINERY_COVERAGES, beneficiaryDetail, fleetListUrl, money, shortDate, unitStatus } from '@/lib/units';

const props = defineProps({
    /** La póliza de maquinaria y sus máquinas, cada una con sus documentos. */
    policy: { type: Object, required: true },
});

const { can } = usePermissions();

const listUrl = fleetListUrl(props.policy.source, true);

const breadcrumbs = [
    { label: 'Inicio', href: '/dashboard' },
    { label: 'Flotillas', href: listUrl },
    { label: `Póliza ${props.policy.policy}` },
];

const status = computed(() => coverageStatus(props.policy.status));

/** Los datos de la póliza, en fichas como los de la unidad. */
const details = computed(() => [
    // En el orden del formulario: de quién es, luego la póliza.
    { label: 'Unidad de negocio', value: props.policy.business_unit, icon: Building2 },
    beneficiaryDetail(props.policy),
    { label: 'Aseguradora', value: props.policy.insurer, icon: Landmark },
    { label: 'Número de póliza', value: props.policy.policy, icon: FileCheck },
    { label: 'Certificado', value: props.policy.certificate, icon: Hash },
    { label: 'Cobertura', value: MACHINERY_COVERAGES[props.policy.coverage]?.label ?? props.policy.coverage, icon: ShieldCheck },
    { label: 'Vigencia', value: `${shortDate(props.policy.valid_from) ?? '—'} – ${shortDate(props.policy.valid_until) ?? '—'}`, icon: CalendarDays },
    // La del proveedor no lleva pago.
    ...(props.policy.source === 'supplier'
        ? []
        : [
              {
                  label: 'Monto a pagar',
                  value: props.policy.amount !== null ? money(props.policy.amount, props.policy.currency ?? 'MXN') : null,
                  icon: CircleDollarSign,
              },
          ]),
]);

/** «22 sep 2026, 14:30» para las marcas de tiempo del registro. */
function dateTime(iso) {
    if (!iso) return '—';

    return new Date(iso).toLocaleString('es-MX', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

const kindMeta = (name) => FILE_KINDS[kindOf(name)] ?? FILE_KINDS.pdf;

const evidenceUrl = (machine, evidence) => `/flotillas/${machine.id}/evidencias/${evidence.id}`;

/** La máquina cuyo modal de documentos está abierto; null = cerrado. */
const documentsMachine = ref(null);

/* ---------- Vista previa ---------- */

const previewOpen = ref(false);
const previewFile = ref(null);

function openPreview(machine, evidence) {
    previewFile.value = { name: evidence.name, size: evidence.size ?? 0, source: evidenceUrl(machine, evidence) };
    previewOpen.value = true;
}

const CARD =
    'rounded-2xl border border-slate-200/80 bg-white p-4 shadow-[0_1px_3px_rgb(2_29_73/0.04),0_8px_24px_-12px_rgb(2_29_73/0.08)] ' +
    'tall:p-5 dark:border-white/[0.08] dark:bg-brand-deep dark:shadow-none';

const SECTION_TITLE = 'text-[0.62rem] font-bold text-slate-400 dark:text-brand-gray/80';

const TILE =
    'mt-0.5 grid size-7 shrink-0 place-content-center rounded-lg bg-slate-50 text-slate-400 ring-1 ring-inset ring-slate-500/10 dark:bg-white/[0.05] dark:text-brand-gray dark:ring-white/10';
</script>

<template>
    <Head :title="`Póliza ${policy.policy}`" />

    <AppShell :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-3 pb-6 tall:gap-4">
            <!-- Encabezado -->
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
                    <span class="inline-flex shrink-0 items-center gap-1 rounded-md px-1.5 py-0.5 text-[0.7rem] font-bold whitespace-nowrap ring-1 ring-inset" :class="status.tone">
                        <component :is="status.icon" class="size-3" />
                        {{ status.label }}
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    <Link
                        v-if="can('flotillas.update')"
                        :href="`/flotillas/maquinaria/${policy.id}/editar`"
                        class="inline-flex h-9 items-center gap-2 rounded-xl bg-brand px-4 text-[0.8rem] font-semibold text-white shadow-md shadow-brand/25 transition-all duration-150 hover:bg-brand/90 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/25 dark:bg-brand-light dark:shadow-black/30 dark:hover:bg-brand-light/90"
                    >
                        <Pencil class="size-4" />
                        Editar
                    </Link>
                    <Link
                        :href="listUrl"
                        class="inline-flex h-9 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-[0.8rem] font-semibold text-slate-700 transition-colors duration-150 hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-500/10 dark:border-white/10 dark:bg-transparent dark:text-brand-gray dark:hover:bg-white/[0.06] dark:hover:text-white"
                    >
                        <ArrowLeft class="size-4" />
                        Volver
                    </Link>
                </div>
            </div>

            <!-- Póliza -->
            <section :class="CARD">
                <p :class="SECTION_TITLE">Póliza</p>

                <dl class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="detail in details" :key="detail.label" class="flex items-start gap-2.5">
                        <span :class="TILE"><component :is="detail.icon" class="size-3.5" /></span>
                        <div class="min-w-0">
                            <dt class="text-[0.6rem] font-bold text-slate-400 dark:text-brand-gray/70">{{ detail.label }}</dt>
                            <dd class="truncate text-[0.82rem] font-semibold text-slate-800 tabular-nums dark:text-white" :title="detail.value ?? undefined">
                                {{ detail.value ?? '—' }}
                            </dd>
                        </div>
                    </div>
                </dl>
            </section>

            <!-- Máquinas: una fila por máquina; sus documentos, en un modal -->
            <section :class="CARD">
                <p :class="SECTION_TITLE">Máquinas ({{ policy.machines.length }})</p>

                <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200 dark:border-white/10">
                    <table class="w-full min-w-[44rem] text-left text-[0.78rem]">
                        <thead class="bg-slate-50 text-[0.62rem] font-bold text-slate-500 dark:bg-white/[0.03] dark:text-brand-gray">
                            <tr>
                                <th class="px-3 py-2 font-bold">Tipo</th>
                                <th class="px-3 py-2 font-bold">Marca / Modelo</th>
                                <th class="px-3 py-2 font-bold"># Económico</th>
                                <th class="px-3 py-2 font-bold">Estado</th>
                                <th class="px-3 py-2 text-center font-bold">Docs</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-white/[0.06]">
                            <tr v-for="machine in policy.machines" :key="machine.id">
                                <td class="px-3 py-2 font-semibold text-slate-800 dark:text-white">{{ machine.machine_type ?? '—' }}</td>
                                <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ machine.brand }} {{ machine.model }}</td>
                                <td class="px-3 py-2 tabular-nums text-slate-600 dark:text-slate-300">{{ machine.economic_number ?? '—' }}</td>
                                <td class="px-3 py-2">
                                    <span
                                        class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[0.68rem] font-bold whitespace-nowrap ring-1 ring-inset"
                                        :class="unitStatus(machine.status).tone"
                                    >
                                        {{ unitStatus(machine.status).label }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <button
                                        type="button"
                                        class="inline-flex h-7 cursor-pointer items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 text-[0.72rem] font-semibold tabular-nums text-slate-600 transition-colors duration-150 hover:bg-slate-50 hover:text-brand focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/10 disabled:pointer-events-none disabled:opacity-50 dark:border-white/10 dark:bg-transparent dark:text-brand-gray dark:hover:bg-white/[0.06] dark:hover:text-white"
                                        :disabled="!machine.documents.length"
                                        :aria-label="`Documentos de ${machine.machine_type ?? 'la máquina'}`"
                                        title="Documentos"
                                        @click="documentsMachine = machine"
                                    >
                                        <Paperclip class="size-3.5" />
                                        {{ machine.documents.length || '—' }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Comentarios -->
            <section :class="CARD">
                <p :class="SECTION_TITLE">Comentarios</p>

                <p v-if="policy.comments" class="mt-3 text-[0.8rem] leading-relaxed whitespace-pre-line text-slate-700 dark:text-slate-200">
                    {{ policy.comments }}
                </p>
                <p v-else class="mt-3 flex items-center gap-2 text-[0.78rem] text-slate-400 dark:text-brand-gray/70">
                    <MessageSquareText class="size-4" />
                    Sin comentarios.
                </p>
            </section>
        </div>

        <!-- Documentos de la máquina elegida: tarjetas con vista previa y descarga -->
        <AppModal
            :open="documentsMachine !== null"
            :title="`Documentos · ${documentsMachine?.machine_type ?? 'Máquina'}`"
            :description="documentsMachine ? [documentsMachine.brand, documentsMachine.model, documentsMachine.economic_number].filter(Boolean).join(' · ') : ''"
            :icon="Paperclip"
            size="lg"
            @update:open="(open) => !open && (documentsMachine = null)"
        >
            <ul v-if="documentsMachine" class="flex flex-wrap gap-2 px-6 pb-6 sm:px-7">
                <li
                    v-for="evidence in documentsMachine.documents"
                    :key="evidence.id"
                    class="relative flex h-28 w-24 flex-col items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-2 pt-3 pb-2 text-center dark:border-white/10 dark:bg-white/[0.03]"
                    :title="`${evidence.name}${evidence.uploaded_by ? ` · ${evidence.uploaded_by}` : ''} · ${dateTime(evidence.created_at)}`"
                >
                    <button
                        type="button"
                        class="absolute inset-0 cursor-pointer rounded-xl transition-colors hover:bg-slate-900/[0.03] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/15 dark:hover:bg-white/[0.04]"
                        :aria-label="`Ver ${evidence.name}`"
                        @click="openPreview(documentsMachine, evidence)"
                    />

                    <span class="grid size-10 shrink-0 place-content-center rounded-lg" :class="kindMeta(evidence.name).tile">
                        <component :is="kindMeta(evidence.name).icon" class="size-5" />
                    </span>

                    <span class="line-clamp-2 w-full text-[0.66rem] leading-tight font-medium break-all text-slate-700 dark:text-slate-200">
                        {{ evidence.name }}
                    </span>

                    <span class="mt-auto text-[0.6rem] tabular-nums text-slate-400 dark:text-brand-gray/70">
                        <span class="font-bold">{{ extensionOf(evidence.name).toUpperCase() }}</span>
                        · {{ evidence.size ? fileSize(evidence.size) : '—' }}
                    </span>

                    <a
                        :href="evidenceUrl(documentsMachine, evidence)"
                        class="absolute top-1 right-1 z-[1] grid size-5 place-content-center rounded text-slate-400 transition-colors hover:bg-brand/[0.07] hover:text-brand dark:hover:bg-white/10 dark:hover:text-white"
                        :aria-label="`Descargar ${evidence.name}`"
                        title="Descargar"
                    >
                        <Download class="size-3.5" />
                    </a>
                </li>
            </ul>
        </AppModal>

        <FilePreviewDialog v-model:open="previewOpen" :file="previewFile" />
    </AppShell>
</template>
