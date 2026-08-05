<script setup>
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    CalendarDays,
    CalendarPlus,
    ChevronLeft,
    ChevronRight,
    ExternalLink,
    Loader2,
    MapPin,
    Pencil,
    RefreshCw,
    Trash2,
    Unlink,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppShell from '@/Layouts/AppShell.vue';
import EventFormDialog from '@/components/app/EventFormDialog.vue';
import { Button } from '@/components/ui/button';
import { useEventDisplay } from '@/composables/useEventDisplay';
import { usePermissions } from '@/composables/usePermissions';
import { useSwal } from '@/composables/useSwal';

const props = defineProps({
    /**
     * Calendario que se está viendo: el propio o uno compartido. null solo
     * cuando no hay ninguno al alcance.
     */
    connection: { type: Object, default: null },
    /** Todos a los que tiene acceso, para el selector. */
    calendars: { type: Array, default: () => [] },
    /** 'delegated' = cada quien conecta la suya · 'application' = la app lee el buzón */
    mode: { type: String, default: 'delegated' },
    timezone: { type: String, default: 'UTC' },
});

const breadcrumbs = [{ label: 'Inicio', href: '/calendario' }, { label: 'Calendario' }];

/** Recarga la página con el calendario elegido; el servidor resuelve el resto. */
function switchCalendar(ownerId) {
    router.get('/calendario', { calendario: ownerId }, { preserveScroll: true });
}

const { confirmDelete, blocks } = useSwal();
const { can } = usePermissions();

/* ---------- Alta y edición ---------- */

const dialogOpen = ref(false);
const dialogEvent = ref(null);

function create() {
    dialogEvent.value = null;
    dialogOpen.value = true;
}

/** La rejilla ya trae el evento completo de Graph: no hace falta ir por él. */
function edit(event) {
    dialogEvent.value = event;
    dialogOpen.value = true;
}

const WEEKDAYS = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

/* ---------- Rejilla del mes ---------- */

const cursor = ref(startOfMonth(new Date()));

function startOfMonth(date) {
    return new Date(date.getFullYear(), date.getMonth(), 1);
}

function key(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(
        date.getDate(),
    ).padStart(2, '0')}`;
}

const monthLabel = computed(() =>
    cursor.value.toLocaleDateString('es-MX', { month: 'long', year: 'numeric' }),
);

/** Semanas completas de lunes a domingo que cubren el mes. */
const days = computed(() => {
    const first = cursor.value;
    const offset = (first.getDay() + 6) % 7; // lunes = 0
    const daysInMonth = new Date(first.getFullYear(), first.getMonth() + 1, 0).getDate();
    const start = new Date(first.getFullYear(), first.getMonth(), 1 - offset);
    const total = Math.ceil((offset + daysInMonth) / 7) * 7;

    return Array.from({ length: total }, (_, i) => {
        const date = new Date(start.getFullYear(), start.getMonth(), start.getDate() + i);

        return {
            date,
            key: key(date),
            day: date.getDate(),
            outside: date.getMonth() !== first.getMonth(),
            today: key(date) === key(new Date()),
        };
    });
});

/* ---------- Eventos ---------- */

const events = ref([]);
const loading = ref(false);
const error = ref(null);

/** Agrupados por día para pintarlos en cada celda. */
const byDay = computed(() =>
    events.value.reduce((map, event) => {
        const day = (event.start ?? '').slice(0, 10);
        (map[day] ??= []).push(event);
        return map;
    }, {}),
);

const { guests, response, longDayLabel, timeLabel, startLabel } = useEventDisplay();

/** Cuántos eventos caben en una celda antes de resumir el resto. */
const PER_CELL = 3;

/* ---------- Tooltip y popover flotantes ---------- */

/**
 * Los dos paneles van en `position: fixed` y fuera de la rejilla: dentro de una
 * celda los recortaría el `overflow-hidden` del contenedor del calendario.
 */
const tip = ref(null);
const tipEvent = ref(null);
const tipStyle = ref({});

const pop = ref(null);
const popDay = ref(null);
const popStyle = ref({});

const popEvents = computed(() => (popDay.value ? (byDay.value[popDay.value.key] ?? []) : []));

/**
 * Coloca un panel bajo el elemento, o encima si no cabe. Se mide después de
 * pintar porque el alto depende del contenido: un evento con seis invitados
 * ocupa el doble que uno sin ninguno.
 */
async function anchorTo(target, panel, style, width) {
    const pad = 10;
    const rect = target.getBoundingClientRect();

    const left = Math.max(pad, Math.min(rect.left, window.innerWidth - width - pad));
    style.value = { left: `${left}px`, top: `${rect.bottom + 6}px` };

    await nextTick();

    const height = panel.value?.offsetHeight ?? 0;
    const fitsBelow = rect.bottom + 6 + height + pad <= window.innerHeight;
    const top = fitsBelow ? rect.bottom + 6 : Math.max(pad, rect.top - height - 6);

    style.value = { left: `${left}px`, top: `${top}px` };
}

function showTip(event, mouseEvent) {
    // El popover abierto manda: un tooltip encima lo taparía.
    if (popDay.value) return;

    tipEvent.value = event;
    anchorTo(mouseEvent.currentTarget, tip, tipStyle, 256);
}

function hideTip() {
    tipEvent.value = null;
}

function openDay(cell, mouseEvent) {
    hideTip();
    popDay.value = popDay.value?.key === cell.key ? null : cell;

    if (popDay.value) anchorTo(mouseEvent.currentTarget, pop, popStyle, 256);
}

function closeDay() {
    popDay.value = null;
}

function onKeydown(e) {
    if (e.key === 'Escape') closeDay();
}

onMounted(() => {
    document.addEventListener('click', closeDay);
    document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', closeDay);
    document.removeEventListener('keydown', onKeydown);
});

// Cambiar de mes o recargar deja los paneles apuntando a nada.
watch(events, () => {
    hideTip();
    closeDay();
});

/** `fresh` salta el caché: es lo que hace útil al botón Actualizar cuando el
 *  cambio se hizo desde Outlook y la app no se enteró. */
async function load(fresh = false) {
    if (!props.connection) return;

    loading.value = true;
    error.value = null;

    try {
        const { data } = await axios.get('/calendario/eventos', {
            params: {
                start: days.value[0].key,
                end: days.value.at(-1).key,
                ...(fresh ? { fresh: 1 } : {}),
                // Sin esto el servidor resolvería el primero de la lista y el
                // selector no cambiaría nada.
                ...(props.connection?.owner_id ? { calendario: props.connection.owner_id } : {}),
            },
        });
        events.value = data.data;
    } catch (e) {
        error.value = e.response?.data?.message ?? 'No se pudieron cargar los eventos.';
    } finally {
        loading.value = false;
    }
}

function move(months) {
    cursor.value = new Date(cursor.value.getFullYear(), cursor.value.getMonth() + months, 1);
}

/**
 * Borra en Outlook y recarga la rejilla. La respuesta del servidor sube la
 * versión del caché, así que el evento ya no vuelve en la siguiente lectura.
 */
async function destroy(event) {
    const invited = guests(event).length;

    const ok = await confirmDelete({
        title: '¿Eliminar evento?',
        html: blocks.stack(
            blocks.lead(
                `<span class="font-medium">${event.title}</span><br>` +
                    `<span class="text-muted-foreground">${longDayLabel(event.start)} · ${timeLabel(event)}</span>`,
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
        data: { event_id: event.id, ...(props.connection ? { calendario: props.connection.owner_id } : {}) },
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => load(true),
    });
}

async function disconnect() {
    const ok = await confirmDelete({
        title: '¿Desvincular cuenta?',
        // Se detalla qué sobrevive: sin eso la gente asume que borra su agenda.
        html: blocks.stack(
            blocks.lead(`Se desconecta <span class="font-medium">${props.connection.email}</span>`),
            blocks.panel({
                label: 'Se pierde',
                tone: 'danger',
                items: [
                    'El acceso guardado a tu cuenta de Microsoft',
                    'La vista del calendario y el alta de eventos, hasta reconectar',
                ],
            }),
            blocks.panel({
                label: 'No se toca',
                items: [
                    'Tus eventos, que siguen en Outlook',
                    'El calendario y con quién está compartido',
                ],
            }),
            blocks.note('Al reconectar se recupera todo, incluida la lista de compartidos.'),
        ),
        confirmText: 'Desvincular',
    });

    if (ok) router.delete('/auth/microsoft');
}

// Envueltas a propósito: watch y onMounted pasan argumentos que load()
// interpretaría como `fresh` y saltarían el caché en cada cambio de mes.
watch(cursor, () => load());
onMounted(() => load());
</script>

<template>
    <Head title="Calendario" />

    <AppShell :breadcrumbs="breadcrumbs">
        <!-- Encabezado -->
        <div class="mb-5 flex flex-wrap items-start justify-between gap-4">
            <div class="flex flex-col gap-3">
                <!-- Icono y título -->
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-content-center rounded-lg bg-accent text-accent-foreground">
                        <CalendarDays class="size-5" />
                    </span>
                    <h1 class="text-2xl font-semibold tracking-tight">Calendario</h1>
                </div>

                <!-- Cuenta registrada -->
                <div class="flex flex-wrap items-center gap-x-6 gap-y-1 text-sm">
                    <p v-if="connection">
                        <span class="text-muted-foreground">Correo principal:</span>
                        <span class="ml-1 font-medium">{{ connection.email }}</span>
                    </p>
                    <p v-else class="text-muted-foreground">Conecta tu cuenta para ver tu agenda</p>

                    <!-- Un calendario ajeno se lee con el token de su dueño: hay
                         que decir de quién es y qué se puede hacer en él -->
                    <span
                        v-if="connection && !connection.own"
                        class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs text-muted-foreground"
                    >
                        <Users class="size-3.5" />
                        Compartido por {{ connection.owner_name }}
                        <span class="font-mono text-[0.65rem] uppercase">· {{ connection.role }}</span>
                    </span>
                </div>

                <!-- Selector: solo estorba cuando hay uno solo -->
                <div v-if="calendars.length > 1" class="flex items-center gap-2 text-sm">
                    <label for="calendario" class="text-muted-foreground">Ver:</label>
                    <select
                        id="calendario"
                        class="h-8 rounded-lg border border-input bg-transparent bg-none px-2.5 py-1 text-sm outline-none transition-colors focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 dark:bg-input/30"
                        :value="connection?.owner_id"
                        @change="switchCalendar($event.target.value)"
                    >
                        <option
                            v-for="option in calendars"
                            :key="option.owner_id"
                            :value="option.owner_id"
                            class="bg-popover text-popover-foreground"
                        >
                            {{ option.own ? 'Mi calendario' : option.owner_name }}
                        </option>
                    </select>
                </div>
            </div>

            <div v-if="connection" class="flex items-center gap-2">
                <!-- En un calendario ajeno solo se escribe con rol `write` -->
                <Button v-if="can('events.create') && connection.can_write" size="sm" @click="create">
                    <CalendarPlus class="size-4" />
                    Nuevo evento
                </Button>
                <Button variant="outline" size="sm" :disabled="loading" @click="load(true)">
                    <RefreshCw class="size-4" :class="loading && 'animate-spin'" />
                    Actualizar
                </Button>
                <!-- Desvincular es del dueño: un invitado no desconecta nada -->
                <Button
                    v-if="mode === 'delegated' && can('calendar.link') && connection.own"
                    variant="outline"
                    size="sm"
                    @click="disconnect"
                >
                    <Unlink class="size-4" />
                    Desvincular
                </Button>
            </div>

            <!-- Redirección fuera de la app: enlace normal, no Inertia -->
            <a
                v-else
                href="/auth/microsoft/redirect"
                class="rounded-md bg-[#459AF7] px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-[#3b86d8]"
            >
                Conectar con outlook
            </a>
        </div>

        <!-- Sin vincular -->
        <div
            v-if="!connection"
            class="grid place-content-center gap-2 rounded-xl border border-dashed bg-card p-16 text-center"
        >
            <CalendarDays class="mx-auto size-8 text-muted-foreground" />
            <p class="font-medium">Tu agenda de Outlook aún no está conectada</p>
            <p class="max-w-md text-sm text-muted-foreground">
                Al conectar, el sistema lee los eventos de tu calendario de Microsoft 365. Es solo
                lectura y puedes desvincular cuando quieras.
            </p>
        </div>

        <!-- Calendario -->
        <template v-else>
            <div class="mb-3 flex items-center gap-2">
                <Button variant="outline" size="icon" aria-label="Mes anterior" @click="move(-1)">
                    <ChevronLeft class="size-4" />
                </Button>
                <Button variant="outline" size="icon" aria-label="Mes siguiente" @click="move(1)">
                    <ChevronRight class="size-4" />
                </Button>
                <Button variant="outline" size="sm" @click="cursor = startOfMonth(new Date())">Hoy</Button>
                <span class="ml-1 text-lg font-medium capitalize">{{ monthLabel }}</span>
                <Loader2 v-if="loading" class="size-4 animate-spin text-muted-foreground" />
            </div>

            <p
                v-if="error"
                class="mb-3 rounded-md border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm text-destructive"
            >
                {{ error }}
                <a v-if="mode === 'delegated'" href="/auth/microsoft/redirect" class="ml-1 underline">
                    Reconectar
                </a>
            </p>

            <div class="overflow-hidden rounded-xl border bg-card">
                <div class="grid grid-cols-7 border-b bg-muted/40 text-xs font-medium text-muted-foreground">
                    <div v-for="day in WEEKDAYS" :key="day" class="px-2 py-2 text-center">{{ day }}</div>
                </div>

                <div class="grid grid-cols-7">
                    <div
                        v-for="cell in days"
                        :key="cell.key"
                        class="min-h-15 border-b border-r p-1.5"
                        :class="cell.outside && 'bg-muted/20'"
                    >
                        <span
                            class="inline-grid size-6 place-content-center rounded-full text-xs"
                            :class="[
                                cell.today && 'bg-primary font-semibold text-primary-foreground',
                                cell.outside && 'text-muted-foreground/50',
                            ]"
                        >
                            {{ cell.day }}
                        </span>

                        <ul class="mt-1 space-y-1">
                            <li
                                v-for="event in (byDay[cell.key] ?? []).slice(0, PER_CELL)"
                                :key="event.id"
                                class="group relative rounded hover:bg-accent"
                            >
                                <!-- Sin `title`: lo sustituye el tooltip, y el nativo del
                                     navegador se encimaría con medio segundo de retraso -->
                                <a
                                    :href="event.url"
                                    target="_blank"
                                    rel="noopener"
                                    class="flex items-center gap-1 rounded px-1 py-0.5 text-[0.7rem] leading-tight"
                                    @mouseenter="showTip(event, $event)"
                                    @mouseleave="hideTip"
                                    @focus="showTip(event, $event)"
                                    @blur="hideTip"
                                >
                                    <span class="size-1.5 shrink-0 rounded-full bg-[#459AF7]" />
                                    <!-- Una sola línea: el título se corta con puntos suspensivos
                                         y el detalle completo lo da el tooltip -->
                                    <span class="min-w-0 flex-1 truncate">
                                        <span class="text-muted-foreground">{{ startLabel(event) }}</span>
                                        <span class="ml-1">{{ event.title }}</span>
                                    </span>
                                    <ExternalLink class="size-3 shrink-0 opacity-0 group-hover:opacity-60" />
                                </a>

                                <!-- Flotan sobre el evento: las celdas no tienen alto para una fila propia -->
                                <div
                                    class="absolute right-0.5 top-0.5 hidden gap-0.5 rounded bg-card/95 p-0.5 shadow-sm group-hover:flex group-focus-within:flex"
                                >
                                    <button
                                        type="button"
                                        class="rounded p-0.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                                        title="Editar"
                                        v-if="can('events.update')"
                                        aria-label="Editar evento"
                                        @click="edit(event)"
                                    >
                                        <Pencil class="size-3" />
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded p-0.5 text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
                                        title="Eliminar"
                                        v-if="can('events.delete')"
                                        aria-label="Eliminar evento"
                                        @click="destroy(event)"
                                    >
                                        <Trash2 class="size-3" />
                                    </button>
                                </div>
                            </li>
                            <li v-if="(byDay[cell.key] ?? []).length > PER_CELL">
                                <button
                                    type="button"
                                    class="w-full rounded px-1 py-0.5 text-left text-[0.7rem] text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                                    :class="popDay?.key === cell.key && 'bg-accent text-foreground'"
                                    @click.stop="openDay(cell, $event)"
                                >
                                    +{{ byDay[cell.key].length - PER_CELL }} más
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Detalle del evento al pasar el cursor -->
            <div
                ref="tip"
                class="pointer-events-none fixed z-40 w-64 rounded-xl border bg-card p-3 shadow-lg transition-opacity duration-150"
                :class="tipEvent ? 'opacity-100' : 'opacity-0'"
                :style="tipStyle"
                aria-hidden="true"
            >
                <template v-if="tipEvent">
                    <p class="text-sm font-medium leading-tight">{{ tipEvent.title }}</p>
                    <p class="mt-0.5 text-xs capitalize text-muted-foreground">
                        {{ longDayLabel(tipEvent.start) }} · {{ timeLabel(tipEvent) }}
                    </p>

                    <p v-if="tipEvent.location" class="mt-1.5 flex items-start gap-1.5 text-xs">
                        <MapPin class="mt-0.5 size-3 shrink-0 text-muted-foreground" />
                        <span>{{ tipEvent.location }}</span>
                    </p>

                    <p v-if="tipEvent.organizer" class="mt-1 text-xs text-muted-foreground">
                        Organiza {{ tipEvent.organizer }}
                    </p>

                    <div v-if="guests(tipEvent).length" class="mt-2 space-y-1 border-t pt-2">
                        <p
                            v-for="guest in guests(tipEvent).slice(0, 5)"
                            :key="guest.email"
                            class="flex items-center gap-1.5 text-xs"
                        >
                            <span class="size-1.5 shrink-0 rounded-full" :class="response(guest).dot" />
                            <span class="min-w-0 truncate">{{ guest.name || guest.email }}</span>
                            <span class="ml-auto shrink-0 text-[0.65rem] text-muted-foreground">
                                {{ response(guest).label }}
                            </span>
                        </p>
                        <p
                            v-if="guests(tipEvent).length > 5"
                            class="text-[0.65rem] text-muted-foreground"
                        >
                            +{{ guests(tipEvent).length - 5 }} invitados más
                        </p>
                    </div>
                </template>
            </div>

            <!-- Todos los eventos del día -->
            <div
                v-show="popDay"
                ref="pop"
                class="fixed z-50 w-64 overflow-hidden rounded-xl border bg-card shadow-xl"
                :style="popStyle"
                @click.stop
            >
                <div class="flex items-center justify-between gap-2 border-b px-3 py-2">
                    <p class="truncate text-xs font-medium capitalize">
                        {{ popDay ? longDayLabel(popDay.key) : '' }}
                    </p>
                    <button
                        type="button"
                        class="rounded p-0.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                        aria-label="Cerrar"
                        @click="closeDay"
                    >
                        <X class="size-3.5" />
                    </button>
                </div>

                <ul class="max-h-64 overflow-y-auto p-1.5">
                    <li v-for="event in popEvents" :key="event.id" class="group/row flex items-start gap-2 rounded p-1.5 hover:bg-accent">
                        <span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-[#459AF7]" />
                        <a
                            :href="event.url"
                            target="_blank"
                            rel="noopener"
                            class="min-w-0 flex-1"
                        >
                            <span class="block truncate text-xs font-medium">{{ event.title }}</span>
                            <span class="block text-[0.65rem] text-muted-foreground">
                                {{ timeLabel(event) }}<template v-if="event.location"> · {{ event.location }}</template>
                            </span>
                        </a>
                        <span class="flex shrink-0 gap-0.5 opacity-0 transition-opacity group-hover/row:opacity-100">
                            <button
                                type="button"
                                class="rounded p-0.5 text-muted-foreground transition-colors hover:bg-background hover:text-foreground"
                                v-if="can('events.update')"
                                aria-label="Editar evento"
                                @click="closeDay(); edit(event)"
                            >
                                <Pencil class="size-3" />
                            </button>
                            <button
                                type="button"
                                class="rounded p-0.5 text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
                                v-if="can('events.delete')"
                                aria-label="Eliminar evento"
                                @click="closeDay(); destroy(event)"
                            >
                                <Trash2 class="size-3" />
                            </button>
                        </span>
                    </li>
                </ul>
            </div>

            <p v-if="connection.synced_at" class="mt-3 text-xs text-muted-foreground">
                Última sincronización: {{ new Date(connection.synced_at).toLocaleString('es-MX') }}
            </p>

            <EventFormDialog
                v-model:open="dialogOpen"
                :event="dialogEvent"
                :calendar="connection?.owner_id"
                @saved="load(true)"
            />
        </template>
    </AppShell>
</template>
