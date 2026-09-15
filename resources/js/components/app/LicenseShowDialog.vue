<script setup>
import { Building2, CalendarDays, CalendarPlus, FileBadge, Landmark, MessageSquareText } from 'lucide-vue-next';
import { DialogClose } from 'reka-ui';
import { computed } from 'vue';
import AppModal from '@/components/app/AppModal.vue';
import { licenseStatus } from '@/lib/licenses';

const props = defineProps({
    open: { type: Boolean, default: false },
    /** Fila de la tabla: { id, name, company, authority, valid_until, status, comments, created_at } */
    license: { type: Object, default: null },
});

const emit = defineEmits(['update:open']);

/** «2026-09-15» en hora local: con new Date(iso) saldría un día antes. */
function localDate(iso) {
    const [y, m, d] = iso.split('-').map(Number);

    return new Date(y, m - 1, d);
}

function longDate(date) {
    return date.toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' });
}

function stamp(iso) {
    return iso ? longDate(new Date(iso)) : '—';
}

/** «Vence en 12 días», «Venció hace 3 días», «Vence hoy». */
const remaining = computed(() => {
    if (!props.license?.valid_until) return 'Sin vigencia: sigue en trámite';

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const days = Math.round((localDate(props.license.valid_until) - today) / 86_400_000);

    if (days === 0) return 'Vence hoy';
    if (days > 0) return days === 1 ? 'Vence mañana' : `Vence en ${days} días`;

    return days === -1 ? 'Venció ayer' : `Venció hace ${-days} días`;
});

const status = computed(() => (props.license ? licenseStatus(props.license.status) : null));

const tiles = computed(() =>
    props.license
        ? [
            { icon: Building2, label: 'Empresa', value: props.license.company ?? '—' },
            { icon: Landmark, label: 'Autoridad', value: props.license.authority ?? '—' },
            {
                icon: CalendarDays,
                label: 'Vigencia',
                value: props.license.valid_until ? longDate(localDate(props.license.valid_until)) : 'Por definir',
                hint: remaining.value,
            },
            { icon: CalendarPlus, label: 'Fecha de alta', value: stamp(props.license.created_at) },
        ]
        : [],
);
</script>

<template>
    <AppModal
        :open="open"
        size="md"
        :icon="FileBadge"
        :title="license?.name ?? ''"
        :description="license?.company ?? 'Sin empresa'"
        @update:open="emit('update:open', $event)"
    >
        <div v-if="license" class="space-y-5 px-6 pt-1 pb-6 short:space-y-4 short:pb-4 sm:px-7">
            <!-- Estatus: lo primero que se busca en una licencia -->
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-bold ring-1 ring-inset" :class="status.tone">
                    <component :is="status.icon" v-if="status.icon" class="size-3.5" />
                    {{ status.label }}
                </span>
                <span class="text-xs text-slate-500 dark:text-brand-gray">{{ remaining }}</span>
            </div>

            <dl class="grid gap-2.5 sm:grid-cols-2">
                <div
                    v-for="tile in tiles"
                    :key="tile.label"
                    class="rounded-xl border border-slate-200/80 bg-slate-50/60 px-3.5 py-3 dark:border-white/[0.08] dark:bg-white/[0.03]"
                >
                    <dt class="flex items-center gap-2 text-[0.62rem] font-bold uppercase tracking-[0.14em] text-slate-400 dark:text-brand-gray/80">
                        <component :is="tile.icon" class="size-3.5" />
                        {{ tile.label }}
                    </dt>
                    <dd class="mt-1.5 truncate text-[0.85rem] font-bold text-slate-800 dark:text-white" :title="tile.value">{{ tile.value }}</dd>
                    <dd v-if="tile.hint" class="mt-0.5 truncate text-[0.7rem] text-slate-500 dark:text-brand-gray">{{ tile.hint }}</dd>
                </div>
            </dl>

            <section>
                <h3 class="mb-2 flex items-center gap-3 text-[0.62rem] font-bold uppercase tracking-[0.16em] text-slate-400 dark:text-brand-gray/80">
                    <MessageSquareText class="size-3.5" />
                    Comentarios
                    <span class="h-px flex-1 bg-slate-100 dark:bg-white/[0.06]" />
                </h3>
                <!-- whitespace-pre-line: respeta los saltos de línea que escribió la persona -->
                <p v-if="license.comments" class="text-[0.8rem] leading-relaxed whitespace-pre-line text-slate-700 dark:text-slate-300">{{ license.comments }}</p>
                <p v-else class="text-[0.8rem] text-slate-400 dark:text-brand-gray/70">Sin comentarios.</p>
            </section>
        </div>

        <template #footer>
            <DialogClose
                class="h-9 cursor-pointer rounded-xl border border-slate-200 bg-white px-4 text-[0.8rem] font-semibold text-slate-700 transition-colors duration-150 hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-500/10 dark:border-white/10 dark:bg-transparent dark:text-brand-gray dark:hover:bg-white/[0.06] dark:hover:text-white"
            >
                Cerrar
            </DialogClose>
        </template>
    </AppModal>
</template>
