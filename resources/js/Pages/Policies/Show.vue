<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Building2,
    CalendarRange,
    FileCheck,
    FileClock,
    FilePlus2,
    FolderOpen,
    HardHat,
    Landmark,
    MapPin,
    MessageSquareText,
    Paperclip,
    Pencil,
    ShieldCheck,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppShell from '@/Layouts/AppShell.vue';
import ConfirmDeleteDialog from '@/components/app/ConfirmDeleteDialog.vue';
import FilePreviewDialog from '@/components/app/FilePreviewDialog.vue';
import PolicyEndorsementDialog from '@/components/app/PolicyEndorsementDialog.vue';
import { usePermissions } from '@/composables/usePermissions';
import { POLICY_COVERAGES, coverageStatus } from '@/lib/coverages';
import { FILE_KINDS, fileSize, kindOf } from '@/lib/files';
import { money, shortDate } from '@/lib/units';

const props = defineProps({
    /** La póliza de obra: datos, obra asegurada, monto desglosado, documentos y endosos. */
    policy: { type: Object, required: true },
});

const breadcrumbs = [
    { label: 'Inicio', href: '/calendario' },
    { label: 'Pólizas y Fianzas', href: '/polizas' },
    { label: `Póliza ${props.policy.policy}` },
];

const { can } = usePermissions();

const status = computed(() => coverageStatus(props.policy.status));

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
    { label: 'Aseguradora', value: props.policy.insurer, icon: Landmark },
    { label: 'Cobertura', value: POLICY_COVERAGES[props.policy.coverage] ?? null, icon: ShieldCheck },
    { label: 'Vigencia', value: range(props.policy.valid_from, props.policy.valid_until), icon: CalendarRange },
]);

/** La obra asegurada. */
const projectDetails = computed(() => [
    { label: 'Obra', value: props.policy.project, icon: HardHat },
    { label: 'Dirección', value: props.policy.project_address, icon: MapPin },
    { label: 'Unidad de negocio', value: props.policy.business_unit, icon: Building2 },
]);

/* ---------- Endosos ---------- */

// El más reciente arriba: es el que marca el fin de vigencia.
const endorsements = computed(() => [...props.policy.endorsements].reverse());

const endorsementOpen = ref(false);

const previewOpen = ref(false);
const previewFile = ref(null);

function openFile(endorsement) {
    previewFile.value = {
        name: endorsement.file.name,
        size: endorsement.file.size ?? 0,
        source: `/polizas/${props.policy.id}/endosos/${endorsement.id}/archivo`,
    };
    previewOpen.value = true;
}

function openDocument(document) {
    previewFile.value = { name: document.name, size: document.size ?? 0, source: `/polizas/${props.policy.id}/documentos/${document.id}` };
    previewOpen.value = true;
}

const fileKind = (name) => FILE_KINDS[kindOf(name)] ?? FILE_KINDS.pdf;

// Solo el último se quita: la vigencia regresa al fin que tenía antes de él.
const deleteOpen = ref(false);
const removing = ref(null);

function askRemove(endorsement) {
    removing.value = endorsement;
    deleteOpen.value = true;
}

function remove() {
    router.delete(`/polizas/${props.policy.id}/endosos/${removing.value.id}`, { preserveScroll: true });
}

const removeText = computed(() =>
    removing.value
        ? `¿Seguro que quieres quitar este endoso? La vigencia regresa a terminar el ${shortDate(removing.value.previous_valid_until)}.`
        : '',
);

/** «22 sep 2026, 14:30» para las marcas de tiempo del registro. */
function dateTime(iso) {
    if (!iso) return '—';

    return new Date(iso).toLocaleString('es-MX', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

const CARD =
    'rounded-2xl border border-slate-200/80 bg-white p-4 shadow-[0_1px_3px_rgb(2_29_73/0.04),0_8px_24px_-12px_rgb(2_29_73/0.08)] ' +
    'tall:p-5 dark:border-white/[0.08] dark:bg-brand-deep dark:shadow-none';

const SECTION_TITLE = 'text-[0.62rem] font-bold text-slate-400 dark:text-brand-gray/80';

const TILE_ICON =
    'mt-0.5 grid size-7 shrink-0 place-content-center rounded-lg bg-slate-50 text-slate-400 ring-1 ring-inset ring-slate-500/10 dark:bg-white/[0.05] dark:text-brand-gray dark:ring-white/10';

const DT = 'text-[0.6rem] font-bold text-slate-400 dark:text-brand-gray/70';

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
                        <h1 class="truncate text-xl font-bold tracking-tight text-brand dark:text-white">{{ policy.policy }}</h1>
                    </div>
                    <span class="inline-flex shrink-0 items-center gap-1 rounded-md px-1.5 py-0.5 text-[0.7rem] font-bold whitespace-nowrap ring-1 ring-inset" :class="status.tone">
                        <component :is="status.icon" class="size-3.5" />
                        {{ status.label }}
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    <Link
                        href="/polizas"
                        class="inline-flex h-9 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-[0.8rem] font-semibold text-slate-700 transition-colors duration-150 hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-500/10 dark:border-white/10 dark:bg-transparent dark:text-brand-gray dark:hover:bg-white/[0.06] dark:hover:text-white"
                    >
                        <ArrowLeft class="size-4" />
                        Volver
                    </Link>
                    <Link
                        v-if="can('polizas.update')"
                        :href="`/polizas/${policy.id}/editar`"
                        class="inline-flex h-9 items-center gap-2 rounded-xl bg-brand px-4 text-[0.8rem] font-semibold text-white shadow-md shadow-brand/25 transition-all duration-150 hover:bg-brand/90 hover:shadow-lg hover:shadow-brand/30 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/25 active:translate-y-px dark:bg-brand-light dark:shadow-black/30 dark:hover:bg-brand-light/90"
                    >
                        <Pencil class="size-4" />
                        Editar
                    </Link>
                </div>
            </div>

            <div class="grid gap-3 tall:gap-4 lg:grid-cols-3">
                <!-- Columna principal -->
                <div class="flex flex-col gap-3 tall:gap-4 lg:col-span-2">
                    <!-- Datos de la póliza -->
                    <section :class="CARD">
                        <p :class="SECTION_TITLE">Datos de la póliza</p>

                        <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                            <div v-for="detail in details" :key="detail.label" class="flex items-start gap-2.5">
                                <span :class="TILE_ICON"><component :is="detail.icon" class="size-3.5" /></span>
                                <div class="min-w-0">
                                    <dt :class="DT">{{ detail.label }}</dt>
                                    <dd :class="DD" :title="detail.value ?? undefined">{{ detail.value ?? '—' }}</dd>
                                </div>
                            </div>
                        </dl>
                    </section>

                    <!-- Obra asegurada -->
                    <section :class="CARD">
                        <p :class="SECTION_TITLE">Obra asegurada</p>

                        <dl class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            <div v-for="detail in projectDetails" :key="detail.label" class="flex items-start gap-2.5">
                                <span :class="TILE_ICON"><component :is="detail.icon" class="size-3.5" /></span>
                                <div class="min-w-0">
                                    <dt :class="DT">{{ detail.label }}</dt>
                                    <dd :class="DD" :title="detail.value ?? undefined">{{ detail.value ?? '—' }}</dd>
                                </div>
                            </div>
                        </dl>
                    </section>

                    <!-- Documentos de la póliza: clic para verlos; se suben y quitan al editar -->
                    <section :class="CARD">
                        <p :class="SECTION_TITLE">Documentos</p>

                        <ul v-if="policy.documents.length" class="mt-3 grid gap-2 sm:grid-cols-2">
                            <li v-for="document in policy.documents" :key="document.id">
                                <button
                                    type="button"
                                    class="flex w-full cursor-pointer items-center gap-2.5 rounded-lg bg-white px-2.5 py-2 text-left text-[0.75rem] ring-1 ring-slate-200 transition-colors hover:ring-brand/30 focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-brand/25 dark:bg-white/[0.04] dark:ring-white/10 dark:hover:ring-white/25"
                                    @click="openDocument(document)"
                                >
                                    <span class="grid size-7 shrink-0 place-content-center rounded-md" :class="fileKind(document.name).tile">
                                        <component :is="fileKind(document.name).icon" class="size-3.5" />
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate font-semibold text-slate-700 dark:text-slate-200" :title="document.name">{{ document.name }}</span>
                                        <span class="block text-[0.66rem] text-slate-400 dark:text-brand-gray/70">
                                            {{ fileSize(document.size ?? 0) }}<template v-if="document.uploaded_by"> · {{ document.uploaded_by }}</template>
                                        </span>
                                    </span>
                                </button>
                            </li>
                        </ul>
                        <p v-else :class="EMPTY">
                            <FolderOpen class="size-4" />
                            Sin documentos.
                        </p>
                    </section>

                    <!-- Endosos de extensión: la misma póliza, con su fin recorrido -->
                    <section :class="CARD">
                        <div class="flex items-center justify-between gap-3">
                            <p :class="SECTION_TITLE">Endosos</p>
                            <button
                                v-if="can('polizas.update')"
                                type="button"
                                class="inline-flex h-7 cursor-pointer items-center gap-1.5 rounded-lg px-2 text-[0.72rem] font-semibold text-brand transition-colors hover:bg-brand/[0.07] focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-brand/25 dark:text-white dark:hover:bg-white/10"
                                @click="endorsementOpen = true"
                            >
                                <FilePlus2 class="size-3.5" />
                                Registrar endoso
                            </button>
                        </div>

                        <ul v-if="endorsements.length" class="mt-3 flex flex-col gap-2">
                            <li
                                v-for="(endorsement, index) in endorsements"
                                :key="endorsement.id"
                                class="flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-slate-50/60 px-3.5 py-2.5 dark:border-white/10 dark:bg-white/[0.03]"
                            >
                                <div class="min-w-0 flex-1">
                                    <p class="text-[0.8rem] font-bold text-slate-800 dark:text-white">
                                        {{ endorsement.endorsement ? `Endoso ${endorsement.endorsement}` : 'Endoso sin número' }}
                                    </p>
                                    <p class="text-[0.72rem] tabular-nums text-slate-500 dark:text-brand-gray">
                                        Extiende del {{ shortDate(endorsement.previous_valid_until) }} al
                                        <span class="font-semibold text-slate-700 dark:text-slate-200">{{ shortDate(endorsement.valid_until) }}</span>
                                        <template v-if="endorsement.amount !== null">
                                            · <span class="font-semibold text-slate-700 dark:text-slate-200">{{ money(endorsement.amount) }}</span>
                                        </template>
                                    </p>
                                    <p class="text-[0.66rem] text-slate-400 dark:text-brand-gray/70">
                                        {{ dateTime(endorsement.created_at) }}<template v-if="endorsement.created_by"> · {{ endorsement.created_by }}</template>
                                    </p>
                                </div>

                                <button
                                    v-if="endorsement.file"
                                    type="button"
                                    class="flex max-w-56 cursor-pointer items-center gap-2 rounded-lg bg-white px-2.5 py-1.5 text-left text-[0.72rem] ring-1 ring-slate-200 transition-colors hover:ring-brand/30 focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-brand/25 dark:bg-white/[0.04] dark:ring-white/10 dark:hover:ring-white/25"
                                    @click="openFile(endorsement)"
                                >
                                    <span class="grid size-6 shrink-0 place-content-center rounded-md" :class="fileKind(endorsement.file.name).tile">
                                        <component :is="fileKind(endorsement.file.name).icon" class="size-3.5" />
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block truncate font-semibold text-slate-700 dark:text-slate-200" :title="endorsement.file.name">{{ endorsement.file.name }}</span>
                                        <span class="block text-[0.64rem] text-slate-400 dark:text-brand-gray/70">{{ fileSize(endorsement.file.size ?? 0) }}</span>
                                    </span>
                                </button>
                                <span v-else class="flex items-center gap-1.5 text-[0.72rem] text-slate-400 dark:text-brand-gray/70">
                                    <Paperclip class="size-3.5" />
                                    Sin documento
                                </span>

                                <!-- Solo el último se puede quitar -->
                                <button
                                    v-if="index === 0 && can('polizas.update')"
                                    type="button"
                                    class="grid size-7 cursor-pointer place-content-center rounded-lg text-slate-400 transition-colors duration-150 hover:bg-red-50 hover:text-red-600 focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-red-500/20 dark:text-brand-gray dark:hover:bg-red-500/10 dark:hover:text-red-300"
                                    aria-label="Quitar endoso"
                                    title="Quitar endoso"
                                    @click="askRemove(endorsement)"
                                >
                                    <Trash2 class="size-3.5" />
                                </button>
                            </li>
                        </ul>
                        <p v-else :class="EMPTY">
                            <FileClock class="size-4" />
                            Sin endosos: la vigencia es la original.
                        </p>
                    </section>
                </div>

                <!-- Columna lateral -->
                <div class="flex flex-col gap-3 tall:gap-4">
                    <!-- Monto desglosado -->
                    <section :class="CARD">
                        <p :class="SECTION_TITLE">Monto a pagar</p>

                        <p class="mt-3 text-2xl font-bold tabular-nums text-brand dark:text-white">{{ money(policy.total) }}</p>
                        <p class="text-[0.7rem] text-slate-400 dark:text-brand-gray/70">IVA incluido<template v-if="policy.endorsements_amount"> · póliza más endosos</template></p>

                        <dl class="mt-3 flex flex-col gap-1.5 border-t border-slate-100 pt-3 text-[0.78rem] dark:border-white/[0.06]">
                            <!-- Con endosos cobrados se ve de dónde sale el total -->
                            <template v-if="policy.endorsements_amount">
                                <div class="flex justify-between gap-3">
                                    <dt class="text-slate-500 dark:text-brand-gray">Póliza</dt>
                                    <dd class="font-semibold tabular-nums text-slate-700 dark:text-slate-200">{{ money(policy.amount) }}</dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt class="text-slate-500 dark:text-brand-gray">Endosos</dt>
                                    <dd class="font-semibold tabular-nums text-slate-700 dark:text-slate-200">{{ money(policy.endorsements_amount) }}</dd>
                                </div>
                            </template>
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

        <PolicyEndorsementDialog v-model:open="endorsementOpen" :policy="policy" />

        <ConfirmDeleteDialog v-model:open="deleteOpen" :text="removeText" @confirm="remove" />
    </AppShell>
</template>
