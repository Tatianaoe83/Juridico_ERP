<script setup>
import { CalendarClock, CalendarPlus, Check, Copy, Fingerprint, Mail, Pencil } from 'lucide-vue-next';
import { DialogClose, DialogDescription, DialogTitle } from 'reka-ui';
import { computed, ref } from 'vue';
import AppModal from '@/components/app/AppModal.vue';
import { roleMeta, roleTone, userInitials } from '@/lib/users';

const props = defineProps({
    open: { type: Boolean, default: false },
    user: { type: Object, default: null },
});

const emit = defineEmits(['update:open', 'edit']);

function longDate(iso) {
    return iso ? new Date(iso).toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' }) : '—';
}

function time(iso) {
    return iso ? new Date(iso).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' }) : '';
}

const role = computed(() => props.user?.roles?.[0] ?? null);

const tiles = computed(() =>
    props.user
        ? [
            { icon: CalendarPlus, label: 'Fecha de alta', value: longDate(props.user.created_at), hint: time(props.user.created_at) },
            { icon: CalendarClock, label: 'Última actualización', value: longDate(props.user.updated_at), hint: time(props.user.updated_at) },
            { icon: Fingerprint, label: 'ID de usuario', value: `#${props.user.id}`, hint: 'Identificador interno' },
            {
                icon: role.value ? roleMeta(role.value).icon : Fingerprint,
                label: 'Permisos',
                value: role.value ? roleMeta(role.value).label : 'Sin rol',
                hint: role.value ? roleMeta(role.value).description : 'No tiene acceso a módulos',
            },
        ]
        : [],
);

/* Copiar el correo: es lo que más se consulta de una ficha. */
const copied = ref(false);

async function copyEmail() {
    try {
        await navigator.clipboard.writeText(props.user.email);
        copied.value = true;
        setTimeout(() => (copied.value = false), 1600);
    } catch {
        // Sin permiso de portapapeles (http sin TLS): el correo sigue visible.
    }
}
</script>

<template>
    <AppModal :open="open" size="md" close-on-dark @update:open="emit('update:open', $event)">
        <!--
            Portada, avatar y nombre van en la cabecera y no en el cuerpo: el
            cuerpo desplaza, y el avatar que monta sobre la portada quedaría
            recortado por su overflow.
        -->
        <template #header>
            <div v-if="user" class="shrink-0">
                <div class="cover h-32 bg-brand short:h-24" aria-hidden="true" />

                <div class="px-6 sm:px-7">
                    <div class="-mt-12 short:-mt-10 flex items-end justify-between gap-4">
                        <span
                            class="grid size-24 short:size-20 short:text-2xl shrink-0 place-content-center rounded-2xl bg-gradient-to-br from-brand-light to-brand text-3xl font-bold text-white shadow-xl shadow-brand/30 ring-[5px] ring-white dark:ring-brand-panel"
                        >
                            {{ userInitials(user.name) }}
                        </span>
                        <span
                            v-if="role"
                            class="mb-1 inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold ring-1 ring-inset"
                            :class="roleTone(role)"
                        >
                            <component :is="roleMeta(role).icon" class="size-3.5" />
                            {{ roleMeta(role).label }}
                        </span>
                    </div>

                    <DialogTitle class="mt-4 short:mt-3 flex flex-wrap items-center gap-2 text-2xl short:text-xl font-bold tracking-tight text-brand dark:text-white">
                        {{ user.name }}
                        <span
                            v-if="user.is_self"
                            class="rounded-full bg-slate-100 px-2 py-0.5 text-[0.65rem] font-bold tracking-wide text-slate-500 uppercase dark:bg-white/10 dark:text-brand-gray"
                        >
                            Tú
                        </span>
                    </DialogTitle>

                    <DialogDescription class="mt-1.5 flex items-center gap-2 text-sm text-slate-500 dark:text-brand-gray">
                        <Mail class="size-4 shrink-0" />
                        <span class="truncate">{{ user.email }}</span>
                        <button
                            type="button"
                            class="grid size-7 shrink-0 cursor-pointer place-content-center rounded-md text-slate-400 transition-colors hover:bg-slate-100 hover:text-brand focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-brand/25 dark:hover:bg-white/10 dark:hover:text-white"
                            :aria-label="copied ? 'Correo copiado' : 'Copiar correo'"
                            :title="copied ? 'Copiado' : 'Copiar correo'"
                            @click="copyEmail"
                        >
                            <Check v-if="copied" class="size-3.5 text-emerald-600 dark:text-emerald-400" />
                            <Copy v-else class="size-3.5" />
                        </button>
                    </DialogDescription>
                </div>
            </div>
        </template>

        <div v-if="user" class="px-6 pt-6 pb-7 short:pt-4 short:pb-5 sm:px-7">
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
                    <dd class="mt-0.5 truncate text-xs text-slate-500 dark:text-brand-gray">{{ tile.hint }}</dd>
                </div>
            </dl>
        </div>

        <template #footer>
            <DialogClose
                class="h-11 short:h-10 cursor-pointer rounded-xl border border-slate-200 bg-white px-5 text-sm font-semibold text-slate-700 transition-colors duration-150 hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-500/10 dark:border-white/10 dark:bg-transparent dark:text-brand-gray dark:hover:bg-white/[0.06] dark:hover:text-white"
            >
                Cerrar
            </DialogClose>
            <button
                v-if="user?.can?.update"
                type="button"
                class="inline-flex h-11 short:h-10 cursor-pointer items-center justify-center gap-2 rounded-xl bg-brand px-5 text-sm font-semibold text-white shadow-lg shadow-brand/25 transition-all duration-150 hover:bg-brand/90 hover:shadow-xl hover:shadow-brand/30 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/25 active:translate-y-px dark:bg-brand-light dark:shadow-black/30 dark:hover:bg-brand-light/90"
                @click="emit('edit', user)"
            >
                <Pencil class="size-4" />
                Editar usuario
            </button>
        </template>
    </AppModal>
</template>

<style scoped>
/* Resplandores y retícula del fondo del login, en pequeño. */
.cover {
    background-image:
        radial-gradient(70% 120% at 0% 100%, rgb(43 77 134 / 0.85), transparent 65%),
        radial-gradient(50% 100% at 100% 0%, rgb(167 168 169 / 0.18), transparent 70%),
        linear-gradient(to right, rgb(255 255 255 / 0.06) 1px, transparent 1px),
        linear-gradient(to bottom, rgb(255 255 255 / 0.06) 1px, transparent 1px);
    background-size: auto, auto, 28px 28px, 28px 28px;
}
</style>
