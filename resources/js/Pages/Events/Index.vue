<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    CalendarCheck,
    CalendarPlus,
    ExternalLink,
    Loader2,
    MapPin,
    Pencil,
    Send,
    Trash2,
    UserPlus,
} from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import AppShell from '@/Layouts/AppShell.vue';
import EventFormDialog from '@/components/app/EventFormDialog.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/composables/usePermissions';
import { useSwal } from '@/composables/useSwal';

const props = defineProps({
    /** Hay un calendario al alcance: el propio o uno compartido. */
    connected: { type: Boolean, default: false },
    /** Dueño, o invitado con rol `write` sobre un calendario ajeno. */
    canWrite: { type: Boolean, default: false },
    /** Solo el dueño reparte accesos. */
    canShare: { type: Boolean, default: false },
    /** El calendario que se está viendo, con su dueño y rol. */
    calendar: { type: Object, default: null },
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

const { confirmDelete, blocks } = useSwal();
const { can } = usePermissions();

/* ---------- Alta y edición ---------- */

// El formulario vive en EventFormDialog: esta página solo lo abre.
const dialogOpen = ref(false);
const dialogEvent = ref(null);

/** La lista ya trae el evento completo de Graph: no hace falta ir por él. */
function edit(event) {
    dialogEvent.value = event;
    dialogOpen.value = true;
}

/** Tras crear o editar hay que releer: Graph es la única fuente. */
function refresh() {
    router.reload({ only: ['upcoming', 'sharedWith'] });
}

async function destroy(event) {
    const invited = guests(event).length;

    const ok = await confirmDelete({
        title: '¿Eliminar evento?',
        html: blocks.stack(
            blocks.lead(
                `<span class="font-medium">${event.title}</span><br>` +
                    `<span class="text-muted-foreground">${dayLabel(event.start)} · ${timeLabel(event)}</span>`,
            ),
            blocks.panel({
                label: 'Se pierde',
                tone: 'danger',
                items: [
                    'El evento desaparece de tu calendario de Outlook',
                    invited
                        ? `Los ${invited} invitados reciben la cancelación`
                        : 'Nadie recibe aviso: el evento no tiene invitados',
                ],
            }),
            blocks.note('No se puede deshacer desde aquí.'),
        ),
    });

    if (!ok) return;

    router.delete('/eventos', {
        data: { event_id: event.id, ...(props.calendar ? { calendario: props.calendar.owner_id } : {}) },
        preserveScroll: true,
    });
}

/* ---------- Lista ---------- */

function dayLabel(iso) {
    const [year, month, day] = (iso ?? '').slice(0, 10).split('-').map(Number);

    return new Date(year, month - 1, day).toLocaleDateString('es-MX', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    });
}

function timeLabel(event) {
    return event.all_day
        ? 'Todo el día'
        : `${(event.start ?? '').slice(11, 16)}–${(event.end ?? '').slice(11, 16)}`;
}

/** Estados de RSVP que devuelve Graph en attendees[].status.response. */
const RESPONSES = {
    accepted: { label: 'Aceptó', dot: 'bg-emerald-500' },
    declined: { label: 'Rechazó', dot: 'bg-red-500' },
    tentativelyAccepted: { label: 'Quizá', dot: 'bg-amber-500' },
    organizer: { label: 'Organizador', dot: 'bg-[#459AF7]' },
    none: { label: 'Sin responder', dot: 'bg-muted-foreground/40' },
};

/** Invitados sin el organizador, que siempre se incluye a sí mismo. */
function guests(event) {
    return (event.attendees ?? []).filter((attendee) => attendee.response !== 'organizer');
}

/** Con fallback: un estado nuevo de Graph no debe romper la vista. */
function response(guest) {
    return RESPONSES[guest.response] ?? RESPONSES.none;
}

// Con ?event= en la URL el diálogo abre directo sobre ese evento.
onMounted(() => {
    if (props.editing) edit(props.editing);
});

/* ---------- Compartir el calendario ---------- */

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

async function unshare(permission) {
    const ok = await confirmDelete({
        title: '¿Revocar acceso?',
        html: blocks.stack(
            blocks.lead(`<span class="font-medium">${permission.email}</span>`),
            blocks.panel({
                label: 'Se pierde',
                tone: 'danger',
                items: [
                    'Deja de ver este calendario y sus eventos',
                    'Desaparece de su Outlook al siguiente refresco',
                ],
            }),
            blocks.note('Puedes volver a compartirlo cuando quieras.'),
        ),
        confirmText: 'Revocar',
    });

    if (!ok) return;

    router.delete('/calendario/compartir', {
        data: { permission_id: permission.id },
        preserveScroll: true,
    });
}

function resend(permission) {
    router.post(
        '/calendario/compartir/reenviar',
        { permission_id: permission.id, email: permission.email, role: permission.role },
        { preserveScroll: true },
    );
}

function roleLabel(role) {
    return ROLES.find((option) => option.value === role)?.label ?? role;
}
</script>

<template>
    <Head title="Compartir" />

    <AppShell :breadcrumbs="breadcrumbs">
        <div class="mb-5 flex items-center gap-3">
            <span class="grid size-10 place-content-center rounded-lg bg-accent text-accent-foreground">
                <CalendarCheck class="size-5" />
            </span>
            <div>
                <h1 class="text-2xl font-semibold tracking-tight"> Compartir</h1>
                <p class="text-sm text-muted-foreground">
                    Se crean directo en tu calendario de Outlook 
                </p>
            </div>
        </div>

        <!-- Sin cuenta vinculada -->
        <div
            v-if="!connected"
            class="grid place-content-center gap-2 rounded-xl border border-dashed bg-card p-16 text-center"
        >
            <CalendarPlus class="mx-auto size-8 text-muted-foreground" />
            <p class="font-medium">Conecta tu cuenta de Outlook</p>
            <p class="max-w-md text-sm text-muted-foreground">
                Para crear eventos hace falta vincular tu cuenta de Microsoft 365.
            </p>
            <a
                href="/auth/microsoft/redirect"
                class="mx-auto mt-2 rounded-md bg-[#459AF7] px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-[#3b86d8]"
            >
                Conectar con Outlook
            </a>
        </div>

        <!-- Invitado de solo lectura: no tiene cuenta que reconectar -->
        <div
            v-else-if="!canWrite && calendar && !calendar.own"
            class="grid place-content-center gap-2 rounded-xl border border-dashed bg-card p-16 text-center"
        >
            <CalendarPlus class="mx-auto size-8 text-muted-foreground" />
            <p class="font-medium">Solo puedes consultar este calendario</p>
            <p class="max-w-md text-sm text-muted-foreground">
                <span class="font-medium text-foreground">{{ calendar.owner_name }}</span> te dio
                acceso de lectura. Para crear eventos necesitas que te suba a
                <code class="rounded bg-muted px-1 py-0.5 text-xs">Editar eventos</code>.
            </p>
        </div>

        <!-- Cuenta propia vinculada, pero sin permiso de escritura -->
        <div
            v-else-if="!canWrite"
            class="grid place-content-center gap-2 rounded-xl border border-dashed bg-card p-16 text-center"
        >
            <CalendarPlus class="mx-auto size-8 text-muted-foreground" />
            <p class="font-medium">Falta permiso para crear eventos</p>
            <p class="max-w-md text-sm text-muted-foreground">
                Tu cuenta se vinculó cuando la app solo pedía lectura. Reconéctala para conceder
                <code class="rounded bg-muted px-1 py-0.5 text-xs">Calendars.ReadWrite</code>.
            </p>
            <a
                href="/auth/microsoft/redirect"
                class="mx-auto mt-2 rounded-md bg-[#459AF7] px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-[#3b86d8]"
            >
                Reconectar cuenta
            </a>
        </div>

        <div v-else class="grid items-start gap-5 lg:grid-cols-3">
            <!-- Próximos -->
            <section class="rounded-xl border bg-card p-5 lg:order-2 min-h-128">
                <h2 class="mb-4 font-medium">Próximos 30 días</h2>

                <p v-if="loadError" class="text-sm text-destructive">{{ loadError }}</p>

                <p v-else-if="!upcoming.length" class="text-sm text-muted-foreground">
                    No hay eventos en el rango.
                </p>

                <ul v-else class="space-y-3">
                    <li
                        v-for="event in upcoming"
                        :key="event.id"
                        class="group/item border-l-2 pl-3"
                        :class="dialogEvent?.id === event.id ? 'border-foreground' : 'border-[#459AF7]'"
                    >
                        <a
                            :href="event.url"
                            target="_blank"
                            rel="noopener"
                            class="group flex items-start gap-1 text-sm font-medium hover:underline"
                        >
                            <span class="min-w-0 truncate">{{ event.title }}</span>
                            <ExternalLink class="mt-0.5 size-3 shrink-0 opacity-0 group-hover:opacity-60" />
                        </a>
                        <p class="text-xs text-muted-foreground">
                            {{ dayLabel(event.start) }} · {{ timeLabel(event) }}
                        </p>
                        <p v-if="event.location" class="flex items-center gap-1 text-xs text-muted-foreground">
                            <MapPin class="size-3 shrink-0" />
                            <span class="truncate">{{ event.location }}</span>
                        </p>

                        <ul v-if="guests(event).length" class="mt-1 space-y-0.5">
                            <li
                                v-for="guest in guests(event)"
                                :key="guest.email"
                                class="flex items-center gap-1.5 text-xs text-muted-foreground"
                                :title="response(guest).label"
                            >
                                <span class="size-1.5 shrink-0 rounded-full" :class="response(guest).dot" />
                                <span class="truncate">{{ guest.name || guest.email }}</span>
                            </li>
                        </ul>

                        <div class="mt-1 flex gap-1 opacity-0 transition-opacity group-hover/item:opacity-100 focus-within:opacity-100">
                            <button
                                type="button"
                                class="flex items-center gap-1 rounded px-1.5 py-0.5 text-xs text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                                @click="edit(event)"
                            >
                                <Pencil class="size-3" />
                                Editar
                            </button>
                            <button
                                type="button"
                                class="flex items-center gap-1 rounded px-1.5 py-0.5 text-xs text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
                                @click="destroy(event)"
                            >
                                <Trash2 class="size-3" />
                                Eliminar
                            </button>
                        </div>
                    </li>
                </ul>
            </section>

            <!-- Compartir -->
            <section class="rounded-xl border bg-card p-5 lg:order-1 lg:col-span-2 ">
                <p class="mb-4 mt-1 text-sm text-muted-foreground">
                El calendario que ves aquí está sincronizado con Outlook. Al agregar a alguien, 
                 le llega una invitación y los cambios se reflejan en ambos lados automáticamente.
                </p>

                <!-- Sin calendario dedicado, compartir expondría la agenda personal -->
                <p
                    v-if="!dedicatedCalendar"
                    class="rounded-md border border-dashed px-3 py-2 text-sm text-muted-foreground"
                >
                    Los eventos viven en tu calendario principal, así que compartirlo expondría toda
                    tu agenda. Crea uno dedicado con
                    <code class="rounded bg-muted px-1 py-0.5 text-xs">php artisan calendario:crear</code>
                    y pon el id en <code class="rounded bg-muted px-1 py-0.5 text-xs">MS_CALENDAR_ID</code>.
                </p>

                <!-- Repartir accesos es del dueño: un invitado no re-comparte -->
                <p
                    v-else-if="!canShare"
                    class="rounded-md border border-dashed px-3 py-2 text-sm text-muted-foreground"
                >
                    Este calendario es de
                    <span class="font-medium text-foreground">{{ calendar?.owner_name }}</span>.
                    Solo quien lo posee puede dar o quitar accesos.
                </p>

                <template v-else>
                    <form v-if="can('calendar.share')" class="flex flex-wrap items-end gap-3" @submit.prevent="submitShare">
                        <div class="grid min-w-56 flex-1 gap-1.5">
                            <Label for="share_email">Correo</Label>
                            <Input
                                id="share_email"
                                v-model="share.email"
                                type="email"
                                required
                                placeholder="alguien@proser.com.mx"
                            />
                            <InputError :message="share.errors.email" />
                        </div>

                        <div class="grid min-w-48 gap-1.5">
                            <Label for="share_role">Puede</Label>
                            <!-- Mismas clases que el componente Input, para que no desentone.
                                 Las opciones llevan color propio: la lista la pinta el sistema
                                 y no hereda el del select, así que salía blanco sobre blanco. -->
                            <select
                                id="share_role"
                                v-model="share.role"
                                class="h-8 w-full min-w-0 rounded-lg border border-input bg-transparent py-1 pl-2.5 pr-8 text-base outline-none transition-colors focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 md:text-sm dark:bg-input/30"
                            >
                                <option
                                    v-for="option in ROLES"
                                    :key="option.value"
                                    :value="option.value"
                                    class="bg-popover text-popover-foreground"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                            <InputError :message="share.errors.role" />
                        </div>

                        <Button type="submit" :disabled="share.processing">
                            <Loader2 v-if="share.processing" class="size-4 animate-spin" />
                            <UserPlus v-else class="size-4" />
                            Compartir
                        </Button>
                    </form>

                    <p v-if="!sharedWith.length" class="mt-4 text-sm text-muted-foreground">
                        Todavía no lo compartes con nadie.
                    </p>

                    <ul v-else class="mt-4 divide-y border-t">
                        <li
                            v-for="permission in sharedWith"
                            :key="permission.id"
                            class="flex items-center gap-3 py-2"
                        >
                            <span class="min-w-0 flex-1 truncate text-sm">{{ permission.email }}</span>
                            <span class="shrink-0 text-xs text-muted-foreground">
                                {{ roleLabel(permission.role) }}
                            </span>
                            <template v-if="permission.removable && can('calendar.share')">
                                <button
                                    type="button"
                                    class="shrink-0 rounded p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                                    :aria-label="`Reenviar invitación a ${permission.email}`"
                                    title="Reenviar invitación"
                                    @click="resend(permission)"
                                >
                                    <Send class="size-3.5" />
                                </button>
                                <button
                                    type="button"
                                    class="shrink-0 rounded p-1 text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
                                    :aria-label="`Revocar acceso de ${permission.email}`"
                                    title="Revocar acceso"
                                    @click="unshare(permission)"
                                >
                                    <Trash2 class="size-3.5" />
                                </button>
                            </template>
                            <span v-else class="shrink-0 text-xs text-muted-foreground">Propietario</span>
                        </li>
                    </ul>
                </template>
            </section>

            <EventFormDialog
                v-model:open="dialogOpen"
                :event="dialogEvent"
                :calendar="calendar?.owner_id"
                @saved="refresh"
            />
        </div>
    </AppShell>
</template>
