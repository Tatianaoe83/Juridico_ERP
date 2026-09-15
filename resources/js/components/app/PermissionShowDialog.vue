<script setup>
import { CalendarPlus, Fingerprint, KeyRound, Layers, Pencil, ShieldCheck } from 'lucide-vue-next';
import { DialogClose } from 'reka-ui';
import { computed } from 'vue';
import AppModal from '@/components/app/AppModal.vue';
import { roleMeta, roleTone } from '@/lib/users';

const props = defineProps({
    open: { type: Boolean, default: false },
    permission: { type: Object, default: null },
});

const emit = defineEmits(['update:open', 'edit']);

function longDate(iso) {
    return iso ? new Date(iso).toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' }) : '—';
}

const tiles = computed(() =>
    props.permission
        ? [
            { icon: Layers, label: 'Área', value: props.permission.area },
            { icon: ShieldCheck, label: 'Roles', value: props.permission.roles.length === 1 ? '1 rol' : `${props.permission.roles.length} roles` },
            { icon: CalendarPlus, label: 'Fecha de alta', value: longDate(props.permission.created_at) },
            { icon: Fingerprint, label: 'ID de permiso', value: `#${props.permission.id}` },
        ]
        : [],
);
</script>

<template>
    <AppModal
        :open="open"
        size="md"
        :icon="KeyRound"
        :title="permission?.label ?? ''"
        :description="permission?.name ?? ''"
        @update:open="emit('update:open', $event)"
    >
        <div v-if="permission" class="space-y-6 px-6 pt-1 pb-7 short:space-y-4 short:pb-5 sm:px-7">
            <dl class="grid gap-3 sm:grid-cols-2">
                <div
                    v-for="tile in tiles"
                    :key="tile.label"
                    class="rounded-xl border border-slate-200/80 bg-slate-50/60 p-4 short:p-3 dark:border-white/[0.08] dark:bg-white/[0.03]"
                >
                    <dt class="flex items-center gap-2 text-[0.68rem] font-bold uppercase tracking-[0.14em] text-slate-400 dark:text-brand-gray/80">
                        <component :is="tile.icon" class="size-3.5" />
                        {{ tile.label }}
                    </dt>
                    <dd class="mt-2 truncate text-[0.95rem] font-bold text-slate-800 dark:text-white" :title="tile.value">
                        {{ tile.value }}
                    </dd>
                </div>
            </dl>

            <section>
                <h3 class="mb-3 flex items-center gap-3 text-[0.68rem] font-bold uppercase tracking-[0.16em] text-slate-400 dark:text-brand-gray/80">
                    <ShieldCheck class="size-3.5" />
                    Roles que lo tienen
                    <span class="h-px flex-1 bg-slate-100 dark:bg-white/[0.06]" />
                </h3>

                <div v-if="permission.roles.length" class="flex flex-wrap gap-1.5">
                    <span
                        v-for="role in permission.roles"
                        :key="role"
                        class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-bold ring-1 ring-inset"
                        :class="roleTone(role)"
                    >
                        <component :is="roleMeta(role).icon" class="size-3.5" />
                        {{ roleMeta(role).label }}
                    </span>
                </div>
                <p v-else class="text-sm text-slate-500 dark:text-brand-gray">Ningún rol lo tiene todavía. Asígnalo desde Roles.</p>
            </section>
        </div>

        <template #footer>
            <DialogClose
                class="h-11 short:h-10 cursor-pointer rounded-xl border border-slate-200 bg-white px-5 text-sm font-semibold text-slate-700 transition-colors duration-150 hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-500/10 dark:border-white/10 dark:bg-transparent dark:text-brand-gray dark:hover:bg-white/[0.06] dark:hover:text-white"
            >
                Cerrar
            </DialogClose>
            <button
                v-if="permission"
                type="button"
                class="inline-flex h-11 short:h-10 cursor-pointer items-center justify-center gap-2 rounded-xl bg-brand px-5 text-sm font-semibold text-white shadow-lg shadow-brand/25 transition-all duration-150 hover:bg-brand/90 hover:shadow-xl hover:shadow-brand/30 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/25 active:translate-y-px dark:bg-brand-light dark:shadow-black/30 dark:hover:bg-brand-light/90"
                @click="emit('edit', permission)"
            >
                <Pencil class="size-4" />
                Editar permiso
            </button>
        </template>
    </AppModal>
</template>
