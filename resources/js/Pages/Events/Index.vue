<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarCheck, CalendarPlus, Eye, Loader2, Lock, MapPin, Pencil, ShieldCheck, Trash2, TriangleAlert, UserPlus, Users } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import AppShell from '@/Layouts/AppShell.vue';
import ConfirmDeleteDialog from '@/components/app/ConfirmDeleteDialog.vue';
import EventShowDialog from '@/components/app/EventShowDialog.vue';
import { useEventDisplay } from '@/composables/useEventDisplay';
import { usePermissions } from '@/composables/usePermissions';

const props = defineProps({
    /** delegated = cada quien su Outlook · application = buzón general. */
    mode: { type: String, default: 'delegated' },
    /** Hay calendario disponible: cuenta vinculada o buzón general configurado. */
    connected: { type: Boolean, default: false },
    /** La vinculación incluye Calendars.ReadWrite. */
    canWrite: { type: Boolean, default: false },
    timezone: { type: String, default: 'UTC' },
    /** Próximos 30 días, tal como los devuelve Graph. */
    upcoming: { type: Array, default: () => [] },
    /** Llega con ?event= — el botón de editar del calendario. */
    editing: { type: Object, default: null },
    /** Quiénes tienen acceso al calendario, según Outlook. */
    sharedWith: { type: Array, default: () => [] },
    /** false = los eventos viven en la agenda personal, compartir no aplica. */
    dedicatedCalendar: { type: Boolean, default: false },
    loadError: { type: String, default: null },
});

const breadcrumbs = [{ label: 'Inicio', href: '/calendario' }, { label: 'Compartir' }];

const { can } = usePermissions();
const { guests, response, dayLabel, timeLabel } = useEventDisplay();

/* ---------- Detalle ---------- */

/**
 * Los eventos se administran en Outlook: aquí solo se consultan. Un clic abre
 * el detalle, sin sacar a nadie de la app.
 */
const detailOpen = ref(false);
const detail = ref(null);

/** La lista ya trae el evento completo de Graph: no hace falta ir por él. */
function show(event) {
    detail.value = event;
    detailOpen.value = true;
}

// Con ?event= en la URL el detalle abre directo sobre ese evento.
onMounted(() => {
    if (props.editing) show(props.editing);
});

/* ---------- Compartir el calendario ---------- */

/**
 * Niveles que devuelve Graph. Aquí solo se reparte `read`: este calendario es
 * de avisos, nadie de fuera tiene por qué mover eventos. Los demás se
 * conservan para poder etiquetar a quien ya tenga otro nivel puesto desde
 * Outlook, y porque los intermedios ni siquiera existen en un buzón personal.
 */
const ROLES = [
    { value: 'read', label: 'Ver los detalles' },
    { value: 'freeBusyRead', label: 'Solo disponibilidad' },
    { value: 'limitedRead', label: 'Título y horario' },
    { value: 'write', label: 'Editar eventos' },
];

const share = useForm({ email: '', role: 'read' });

function submitShare() {
    share.post('/calendario/compartir', {
        preserveScroll: true,
        onSuccess: () => share.reset('email'),
    });
}

const unshareOpen = ref(false);
const toUnshare = ref(null);

function askUnshare(permission) {
    toUnshare.value = permission;
    unshareOpen.value = true;
}

function unshare() {
    const permission = toUnshare.value;

    if (!permission) return;

    router.delete('/calendario/compartir', { data: { permission_id: permission.id }, preserveScroll: true });
}

/** Qué pierde quien deja de tener el calendario compartido. */
const unshareText = computed(() =>
    toUnshare.value
        ? `${toUnshare.value.email} deja de ver este calendario y sus eventos, y desaparece de su Outlook al siguiente refresco. Puedes volver a compartirlo cuando quieras.`
        : '',
);

function roleLabel(role) {
    return ROLES.find((option) => option.value === role)?.label ?? role;
}

/** Quien puede escribir manda sobre quien solo mira: se distingue en la lista. */
const canEditRole = (role) => role === 'write';

const people = computed(() => props.sharedWith.filter((permission) => permission.removable));

/* ---------- Medidas ---------- */

const CARD =
    'rounded-2xl border border-slate-200/80 bg-white shadow-[0_1px_3px_rgb(2_29_73/0.04),0_8px_24px_-12px_rgb(2_29_73/0.08)] ' +
    'dark:border-white/[0.08] dark:bg-brand-deep dark:shadow-none';

const SECTION_TITLE = 'flex items-center gap-2 text-[0.62rem] font-bold uppercase tracking-[0.16em] text-slate-400 dark:text-brand-gray/80';

const FIELD =
    'peer h-9 w-full rounded-lg border border-slate-200 bg-slate-50/70 px-3 text-[0.8rem] text-slate-900 outline-none ' +
    'transition-[border-color,background-color,box-shadow] duration-150 placeholder:text-slate-400 ' +
    'hover:border-slate-300 focus:border-brand/50 focus:bg-white focus:ring-4 focus:ring-brand/10 ' +
    'aria-invalid:border-red-400 aria-invalid:focus:ring-red-500/10 ' +
    'dark:border-white/10 dark:bg-white/[0.04] dark:text-white dark:placeholder:text-white/30 dark:hover:border-white/20 ' +
    'dark:focus:border-brand-gray/50 dark:focus:bg-white/[0.06] dark:focus:ring-white/10 dark:[color-scheme:dark]';

const LABEL = 'mb-1.5 block text-[0.75rem] font-semibold text-slate-700 dark:text-slate-200';

const ERROR = 'mt-1 text-[0.7rem] font-medium text-red-600 dark:text-red-400';

const PRIMARY_BTN =
    'inline-flex h-9 cursor-pointer items-center justify-center gap-2 rounded-xl bg-brand px-4 text-[0.8rem] font-semibold text-white ' +
    'shadow-md shadow-brand/25 transition-all duration-150 hover:bg-brand/90 hover:shadow-lg hover:shadow-brand/30 focus-visible:outline-none ' +
    'focus-visible:ring-4 focus-visible:ring-brand/25 active:translate-y-px disabled:pointer-events-none disabled:opacity-60 ' +
    'dark:bg-brand-light dark:shadow-black/30 dark:hover:bg-brand-light/90';

const DANGER_ACTION =
    'grid size-7 shrink-0 cursor-pointer place-content-center rounded-lg text-slate-400 transition-colors duration-150 ' +
    'hover:bg-red-50 hover:text-red-600 focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-red-500/25 ' +
    'dark:text-brand-gray dark:hover:bg-red-500/10 dark:hover:text-red-400';
</script>

<template>
    <Head title="Compartir" />

    <AppShell :breadcrumbs="breadcrumbs">
        <!-- Encabezado -->
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3 tall:mb-4">
            <div class="flex items-center gap-3">
                <span
                    class="grid size-9 place-content-center rounded-xl bg-gradient-to-br from-brand-light to-brand text-white shadow-md shadow-brand/25 ring-1 ring-white/10"
                >
                    <CalendarCheck class="size-4" />
                </span>
                <div>
                    <p class="text-[0.6rem] font-bold uppercase tracking-[0.2em] text-slate-400 dark:text-brand-gray/80">Microsoft 365</p>
                    <h1 class="text-xl font-bold tracking-tight text-brand dark:text-white">Compartir</h1>
                </div>
            </div>

        </div>

        <!-- Sin cuenta vinculada -->
        <div
            v-if="!connected"
            class="grid place-content-center gap-2 rounded-2xl border border-dashed border-slate-300 bg-white p-16 text-center dark:border-white/15 dark:bg-brand-deep"
        >
            <CalendarPlus class="mx-auto size-8 text-slate-300 dark:text-white/25" />
            <template v-if="mode === 'delegated'">
                <p class="font-bold text-slate-800 dark:text-white">Conecta tu cuenta de Outlook</p>
                <p class="max-w-md text-[0.8rem] leading-relaxed text-slate-500 dark:text-brand-gray">
                    Para crear eventos y compartir el calendario hace falta vincular tu cuenta de Microsoft 365.
                </p>
                <a href="/auth/microsoft/redirect" :class="[PRIMARY_BTN, 'mx-auto mt-2']">Conectar con Outlook</a>
            </template>
            <template v-else>
                <p class="font-bold text-slate-800 dark:text-white">El calendario general no está configurado</p>
                <p class="max-w-md text-[0.8rem] leading-relaxed text-slate-500 dark:text-brand-gray">
                    Falta indicar el buzón general en
                    <code class="rounded bg-slate-100 px-1 py-0.5 text-xs dark:bg-white/10">MS_MAILBOX</code>. Avisa al administrador.
                </p>
            </template>
        </div>

        <!-- Vinculada, pero sin permiso de escritura -->
        <div
            v-else-if="!canWrite"
            class="grid place-content-center gap-2 rounded-2xl border border-dashed border-slate-300 bg-white p-16 text-center dark:border-white/15 dark:bg-brand-deep"
        >
            <ShieldCheck class="mx-auto size-8 text-slate-300 dark:text-white/25" />
            <p class="font-bold text-slate-800 dark:text-white">Falta permiso para crear eventos</p>
            <p class="max-w-md text-[0.8rem] leading-relaxed text-slate-500 dark:text-brand-gray">
                Tu cuenta se vinculó cuando la app solo pedía lectura. Reconéctala para conceder
                <code class="rounded bg-slate-100 px-1 py-0.5 text-xs dark:bg-white/10">Calendars.ReadWrite</code>.
            </p>
            <a href="/auth/microsoft/redirect" :class="[PRIMARY_BTN, 'mx-auto mt-2']">Reconectar cuenta</a>
        </div>

        <div v-else class="grid items-start gap-3 tall:gap-4 lg:grid-cols-3">
            <!-- Acceso al calendario -->
            <section :class="[CARD, 'lg:col-span-2']">
                <div class="border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]">
                    <h2 :class="SECTION_TITLE">
                        <Users class="size-3.5" />
                        Quién tiene acceso
                    </h2>
                    <p class="mt-1.5 text-[0.78rem] leading-relaxed text-slate-500 dark:text-brand-gray">
                        Este calendario está sincronizado con Outlook. A quien agregues le llega una invitación, y a partir de ahí ve los
                        cambios sin que la app haga nada.
                    </p>
                </div>

                <!-- Sin calendario dedicado, compartir expondría la agenda personal -->
                <div v-if="!dedicatedCalendar" class="p-5">
                    <div class="flex gap-2.5 rounded-xl border border-amber-300/60 bg-amber-50 px-3.5 py-3 dark:border-amber-400/25 dark:bg-amber-400/10">
                        <TriangleAlert class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-300" />
                        <p class="text-xs leading-relaxed text-amber-800 dark:text-amber-200/90">
                            Los eventos viven en tu calendario principal, así que compartirlo expondría toda tu agenda. Crea uno dedicado con
                            <code class="rounded bg-amber-100 px-1 py-0.5 dark:bg-white/10">php artisan calendario:crear</code>
                            y pon el id en <code class="rounded bg-amber-100 px-1 py-0.5 dark:bg-white/10">MS_CALENDAR_ID</code>.
                        </p>
                    </div>
                </div>

                <template v-else>
                    <form v-if="can('calendar.share')" class="flex flex-wrap items-start gap-3 px-5 py-4" @submit.prevent="submitShare">
                        <div class="min-w-56 flex-1">
                            <label for="share_email" :class="LABEL">Correo</label>
                            <input
                                id="share_email"
                                v-model="share.email"
                                type="email"
                                required
                                autocomplete="off"
                                placeholder="alguien@proser.com.mx"
                                :class="FIELD"
                                :aria-invalid="Boolean(share.errors.email)"
                            />
                            <p v-if="share.errors.email" :class="ERROR">{{ share.errors.email }}</p>
                            <p v-else class="mt-1 flex items-center gap-1 text-[0.7rem] text-slate-400 dark:text-brand-gray/80">
                                <Eye class="size-3 shrink-0" />
                                Se comparte en solo lectura: ve los eventos, no los edita.
                            </p>
                        </div>

                        <div>
                            <span :class="LABEL" aria-hidden="true">&nbsp;</span>
                            <button type="submit" :class="PRIMARY_BTN" :disabled="share.processing">
                                <Loader2 v-if="share.processing" class="size-4 animate-spin" />
                                <UserPlus v-else class="size-4" />
                                Compartir
                            </button>
                        </div>
                    </form>

                    <p v-if="!people.length" class="px-5 pb-5 text-[0.8rem] text-slate-400 dark:text-brand-gray/70">
                        Todavía no lo compartes con nadie.
                    </p>

                    <ul v-else class="divide-y divide-slate-100 border-t border-slate-100 dark:divide-white/[0.06] dark:border-white/[0.06]">
                        <li v-for="permission in people" :key="permission.id" class="flex items-center gap-3 px-5 py-2.5">
                            <span
                                class="grid size-8 shrink-0 place-content-center rounded-lg bg-brand/[0.07] text-[0.7rem] font-bold text-brand uppercase ring-1 ring-inset ring-brand/15 dark:bg-white/10 dark:text-white dark:ring-white/10"
                                aria-hidden="true"
                            >
                                {{ permission.email[0] }}
                            </span>

                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[0.82rem] font-semibold text-slate-800 dark:text-white">{{ permission.email }}</span>
                                <span v-if="permission.name" class="block truncate text-[0.7rem] text-slate-400 dark:text-brand-gray/80">
                                    {{ permission.name }}
                                </span>
                            </span>

                            <!-- El nivel se lee, no se adivina por el color -->
                            <span
                                class="hidden shrink-0 items-center gap-1 rounded-md px-2 py-0.5 text-[0.7rem] font-semibold ring-1 ring-inset sm:inline-flex"
                                :class="
                                    canEditRole(permission.role)
                                        ? 'bg-brand/[0.07] text-brand ring-brand/15 dark:bg-white/10 dark:text-white dark:ring-white/10'
                                        : 'bg-slate-50 text-slate-600 ring-slate-500/15 dark:bg-white/[0.05] dark:text-brand-gray dark:ring-white/10'
                                "
                            >
                                <Pencil v-if="canEditRole(permission.role)" class="size-3" />
                                {{ roleLabel(permission.role) }}
                            </span>

                            <button
                                v-if="can('calendar.share')"
                                type="button"
                                :class="DANGER_ACTION"
                                :aria-label="`Revocar acceso de ${permission.email}`"
                                title="Revocar acceso"
                                @click="askUnshare(permission)"
                            >
                                <Trash2 class="size-3.5" />
                            </button>
                        </li>
                    </ul>

                    <p class="flex items-start gap-1.5 px-5 py-3 text-[0.7rem] leading-relaxed text-slate-400 dark:text-brand-gray/80">
                        <Lock class="mt-0.5 size-3 shrink-0" />
                        <span>El propietario del calendario no aparece en la lista: su acceso no se puede revocar.</span>
                    </p>
                </template>
            </section>

            <!-- Próximos 30 días -->
            <section :class="[CARD, 'lg:sticky lg:top-4']">
                <div class="border-b border-slate-100 px-5 py-4 dark:border-white/[0.06]">
                    <h2 :class="SECTION_TITLE">
                        <CalendarCheck class="size-3.5" />
                        Próximos 30 días
                        <span
                            v-if="upcoming.length"
                            class="ml-auto rounded-md bg-slate-100 px-1.5 py-0.5 text-[0.65rem] tabular-nums text-slate-600 dark:bg-white/10 dark:text-white"
                        >
                            {{ upcoming.length }}
                        </span>
                    </h2>
                </div>

                <p v-if="loadError" class="flex items-start gap-2 px-5 py-4 text-[0.8rem] text-red-600 dark:text-red-400" role="alert">
                    <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                    <span>{{ loadError }}</span>
                </p>

                <p v-else-if="!upcoming.length" class="px-5 py-8 text-center text-[0.8rem] text-slate-400 dark:text-brand-gray/70">
                    No hay eventos en el rango.
                </p>

                <ul v-else class="max-h-[32rem] divide-y divide-slate-100 overflow-y-auto dark:divide-white/[0.06]">
                    <li v-for="event in upcoming" :key="event.id">
                        <!-- La fila entera es el botón: nada de acciones que aparecen al pasar el cursor -->
                        <button
                            type="button"
                            class="flex w-full cursor-pointer items-start gap-2.5 px-5 py-3 text-left transition-colors duration-150 hover:bg-slate-50/70 focus-visible:outline-none focus-visible:bg-slate-50 focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand/30 dark:hover:bg-white/[0.03] dark:focus-visible:bg-white/[0.04]"
                            :aria-label="`Ver ${event.title}`"
                            @click="show(event)"
                        >
                            <span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-brand dark:bg-brand-gray" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[0.82rem] font-semibold text-slate-800 dark:text-white" :title="event.title">
                                    {{ event.title }}
                                </span>
                                <span class="block text-[0.7rem] capitalize tabular-nums text-slate-500 dark:text-brand-gray">
                                    {{ dayLabel(event.start) }} · {{ timeLabel(event) }}
                                </span>
                                <span v-if="event.location" class="flex items-center gap-1 text-[0.7rem] text-slate-400 dark:text-brand-gray/80">
                                    <MapPin class="size-3 shrink-0" />
                                    <span class="truncate">{{ event.location }}</span>
                                </span>

                                <!-- Resumen: el detalle de cada invitado vive en el modal -->
                                <span v-if="guests(event).length" class="mt-1.5 flex items-center gap-1.5">
                                    <span class="flex -space-x-1">
                                        <span
                                            v-for="guest in guests(event).slice(0, 4)"
                                            :key="guest.email"
                                            class="size-2 rounded-full ring-2 ring-white dark:ring-brand-deep"
                                            :class="response(guest).dot"
                                            :title="`${guest.name || guest.email}: ${response(guest).label}`"
                                        />
                                    </span>
                                    <span class="text-[0.68rem] text-slate-400 dark:text-brand-gray/80">
                                        {{ guests(event).length }} {{ guests(event).length === 1 ? 'invitado' : 'invitados' }}
                                    </span>
                                </span>
                            </span>
                        </button>
                    </li>
                </ul>
            </section>

            <EventShowDialog v-model:open="detailOpen" :event="detail" />

            <ConfirmDeleteDialog v-model:open="unshareOpen" :text="unshareText" @confirm="unshare" />
        </div>
    </AppShell>
</template>
