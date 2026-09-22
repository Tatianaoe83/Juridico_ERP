<script setup>
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { CalendarDays, ChevronLeft, ChevronRight, Loader2, RefreshCw, TriangleAlert, Unlink, X } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppShell from '@/Layouts/AppShell.vue';
import EventShowDialog from '@/components/app/EventShowDialog.vue';
import { useEventDisplay } from '@/composables/useEventDisplay';
import { usePermissions } from '@/composables/usePermissions';
import { useSwal } from '@/composables/useSwal';

/**
 * Agenda del mes, de solo lectura.
 *
 * La agenda se administra en Outlook: aquí se consulta. Un evento abre su
 * detalle en un modal —y no Outlook en otra pestaña— porque sacar a la persona
 * de la app para leer una hora es un viaje de ida.
 */
const props = defineProps({
    /** null cuando el usuario aún no vincula su cuenta de Microsoft. */
    connection: { type: Object, default: null },
    /** 'delegated' = cada quien conecta la suya · 'application' = la app lee el buzón */
    mode: { type: String, default: 'delegated' },
    timezone: { type: String, default: 'UTC' },
});

const breadcrumbs = [{ label: 'Inicio', href: '/calendario' }, { label: 'Calendario' }];

const { confirmDelete, blocks } = useSwal();
const { can } = usePermissions();
const { guests, longDayLabel, timeLabel, startLabel } = useEventDisplay();

const WEEKDAYS = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

/* ---------- Rejilla del mes ---------- */

const cursor = ref(startOfMonth(new Date()));

function startOfMonth(date) {
    return new Date(date.getFullYear(), date.getMonth(), 1);
}

function key(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

const monthLabel = computed(() => cursor.value.toLocaleDateString('es-MX', { month: 'long', year: 'numeric' }));

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

/** Cuántos eventos caben en una celda antes de resumir el resto. */
const PER_CELL = 3;

/**
 * Cinco o seis semanas según el mes. Las filas se reparten el alto del card en
 * partes iguales para que la rejilla lo llene sin dejar hueco abajo ni estirar
 * de más un mes corto.
 */
const weekRows = computed(() => ({ gridTemplateRows: `repeat(${days.value.length / 7}, minmax(0, 1fr))` }));

/* ---------- Detalle ---------- */

const detailOpen = ref(false);
const detail = ref(null);

/** La rejilla ya trae el evento completo de Graph: no hace falta ir por él. */
function show(event) {
    closeDay();
    detail.value = event;
    detailOpen.value = true;
}

/* ---------- Todos los eventos de un día ---------- */

/**
 * El panel va en `position: fixed` y fuera de la rejilla: dentro de una celda
 * lo recortaría el `overflow-hidden` del contenedor del calendario.
 */
const pop = ref(null);
const popDay = ref(null);
const popStyle = ref({});

const popEvents = computed(() => (popDay.value ? (byDay.value[popDay.value.key] ?? []) : []));

/**
 * Coloca el panel bajo el elemento, o encima si no cabe. Se mide después de
 * pintar porque el alto depende de cuántos eventos tenga el día.
 */
async function anchorTo(target, panel, style, width) {
    const pad = 10;
    const rect = target.getBoundingClientRect();
    const left = Math.max(pad, Math.min(rect.left, window.innerWidth - width - pad));

    style.value = { left: `${left}px`, top: `${rect.bottom + 6}px` };

    await nextTick();

    const height = panel.value?.offsetHeight ?? 0;
    const fitsBelow = rect.bottom + 6 + height + pad <= window.innerHeight;

    style.value = { left: `${left}px`, top: `${fitsBelow ? rect.bottom + 6 : Math.max(pad, rect.top - height - 6)}px` };
}

function openDay(cell, mouseEvent) {
    popDay.value = popDay.value?.key === cell.key ? null : cell;

    if (popDay.value) anchorTo(mouseEvent.currentTarget, pop, popStyle, 272);
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

// Cambiar de mes o recargar deja el panel apuntando a nada.
watch(events, closeDay);

/**
 * `fresh` salta el caché: es lo que hace útil al botón Actualizar cuando el
 * cambio se hizo desde Outlook y la app no se enteró.
 */
/**
 * Cada carga lleva su número. Solo la última manda: pasando meses rápido, una
 * petición vieja puede contestar después que la nueva, y sin esto pisaba los
 * eventos del mes que ya se está viendo —se veían aparecer y desaparecer—.
 */
let lastLoad = 0;

async function load(fresh = false) {
    if (!props.connection) return;

    const token = ++lastLoad;

    loading.value = true;
    error.value = null;

    try {
        const { data } = await axios.get('/calendario/eventos', {
            params: {
                start: days.value[0].key,
                end: days.value.at(-1).key,
                ...(fresh ? { fresh: 1 } : {}),
            },
        });

        if (token !== lastLoad) return;

        events.value = data.data;
    } catch (e) {
        if (token !== lastLoad) return;

        error.value = e.response?.data?.message ?? 'No se pudieron cargar los eventos.';
    } finally {
        // El spinner se apaga solo cuando la que llegó es la última pedida.
        if (token === lastLoad) loading.value = false;
    }
}

function move(months) {
    cursor.value = new Date(cursor.value.getFullYear(), cursor.value.getMonth() + months, 1);
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
                items: ['El acceso guardado a tu cuenta de Microsoft', 'La vista del calendario, hasta reconectar'],
            }),
            blocks.panel({
                label: 'No se toca',
                items: ['Tus eventos, que siguen en Outlook', 'El calendario y con quién está compartido'],
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

/* ---------- Medidas ---------- */

const NAV_BTN =
    'grid size-9 cursor-pointer place-content-center rounded-lg border border-slate-200 bg-white text-slate-500 transition-colors duration-150 ' +
    'hover:border-slate-300 hover:bg-slate-50 hover:text-brand focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/15 ' +
    'dark:border-white/10 dark:bg-white/[0.04] dark:text-brand-gray dark:hover:bg-white/[0.08] dark:hover:text-white';

const GHOST_BTN =
    'inline-flex h-9 cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-[0.8rem] font-semibold text-slate-600 ' +
    'transition-colors duration-150 hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-4 ' +
    'focus-visible:ring-brand/15 disabled:pointer-events-none disabled:opacity-50 dark:border-white/10 dark:bg-white/[0.04] dark:text-brand-gray ' +
    'dark:hover:bg-white/[0.08] dark:hover:text-white';

// Chip del evento: el punto de marca lo ancla al calendario de la app.
const CHIP =
    'flex w-full cursor-pointer items-center gap-1.5 rounded-md px-1.5 py-1 text-left text-[0.7rem] leading-tight transition-colors duration-150 ' +
    'hover:bg-brand/[0.07] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/30 dark:hover:bg-white/[0.08]';
</script>

<template>
    <Head title="Calendario" />

    <AppShell :breadcrumbs="breadcrumbs">
        <div class="flex flex-col md:h-full md:min-h-0">
            <!-- Encabezado -->
            <div class="mb-3 flex shrink-0 flex-wrap items-center justify-between gap-3 tall:mb-4">
                <div class="flex items-center gap-3">
                    <span
                        class="grid size-9 place-content-center rounded-xl bg-gradient-to-br from-brand-light to-brand text-white shadow-md shadow-brand/25 ring-1 ring-white/10"
                    >
                        <CalendarDays class="size-4" />
                    </span>
                    <div>
                        <p class="text-[0.6rem] font-bold uppercase tracking-[0.2em] text-slate-400 dark:text-brand-gray/80">Microsoft 365</p>
                        <h1 class="text-xl font-bold tracking-tight text-brand dark:text-white">Calendario</h1>
                    </div>
                </div>

                <div v-if="connection" class="flex items-center gap-2">
                    <button type="button" :class="GHOST_BTN" :disabled="loading" @click="load(true)">
                        <RefreshCw class="size-4" :class="loading && 'animate-spin'" />
                        Actualizar
                    </button>
                    <button
                        v-if="mode === 'delegated' && can('calendar.link')"
                        type="button"
                        :class="GHOST_BTN"
                        @click="disconnect"
                    >
                        <Unlink class="size-4" />
                        Desvincular
                    </button>
                </div>

                <!-- Redirección fuera de la app: enlace normal, no Inertia -->
                <a
                    v-else-if="mode === 'delegated'"
                    href="/auth/microsoft/redirect"
                    class="inline-flex h-9 items-center gap-2 rounded-xl bg-brand px-4 text-[0.8rem] font-semibold text-white shadow-md shadow-brand/25 transition-all duration-150 hover:bg-brand/90 hover:shadow-lg hover:shadow-brand/30 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/25 active:translate-y-px dark:bg-brand-light dark:shadow-black/30 dark:hover:bg-brand-light/90"
                >
                    Conectar con Outlook
                </a>
            </div>

            <!-- Sin vincular -->
            <div
                v-if="!connection"
                class="grid place-content-center gap-2 rounded-2xl border border-dashed border-slate-300 bg-white p-16 text-center dark:border-white/15 dark:bg-brand-deep"
            >
                <CalendarDays class="mx-auto size-8 text-slate-300 dark:text-white/25" />
                <template v-if="mode === 'delegated'">
                    <p class="font-bold text-slate-800 dark:text-white">Tu agenda de Outlook aún no está conectada</p>
                    <p class="max-w-md text-[0.8rem] leading-relaxed text-slate-500 dark:text-brand-gray">
                        Al conectar, el sistema lee los eventos de tu calendario de Microsoft 365. Es solo lectura y puedes desvincular cuando quieras.
                    </p>
                </template>
                <template v-else>
                    <p class="font-bold text-slate-800 dark:text-white">El calendario general no está configurado</p>
                    <p class="max-w-md text-[0.8rem] leading-relaxed text-slate-500 dark:text-brand-gray">
                        Falta indicar el buzón general en
                        <code class="rounded bg-slate-100 px-1 py-0.5 text-xs dark:bg-white/10">MS_MAILBOX</code>. Avisa al administrador.
                    </p>
                </template>
            </div>

            <!-- Calendario -->
            <template v-else>
                <p
                    v-if="error"
                    class="mb-3 flex items-center gap-2 rounded-xl border border-red-300/60 bg-red-50 px-3.5 py-2.5 text-[0.8rem] text-red-700 dark:border-red-400/25 dark:bg-red-400/10 dark:text-red-300"
                    role="alert"
                >
                    <TriangleAlert class="size-4 shrink-0" />
                    <span>{{ error }}</span>
                    <a v-if="mode === 'delegated'" href="/auth/microsoft/redirect" class="ml-auto font-semibold underline underline-offset-2">
                        Reconectar
                    </a>
                </p>

                <div
                    class="@container flex flex-col overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_1px_3px_rgb(2_29_73/0.04),0_8px_24px_-12px_rgb(2_29_73/0.08)] md:min-h-0 md:flex-1 dark:border-white/[0.08] dark:bg-brand-deep dark:shadow-none"
                >
                    <!-- Barra: mes y navegación -->
                    <div class="flex shrink-0 flex-wrap items-center gap-2 px-3 py-2.5 tall:py-3 @2xl:px-4 @6xl:px-6">
                        <button type="button" :class="NAV_BTN" aria-label="Mes anterior" @click="move(-1)">
                            <ChevronLeft class="size-4" />
                        </button>
                        <button type="button" :class="NAV_BTN" aria-label="Mes siguiente" @click="move(1)">
                            <ChevronRight class="size-4" />
                        </button>
                        <button type="button" :class="GHOST_BTN" @click="cursor = startOfMonth(new Date())">Hoy</button>

                        <h2 class="ml-1 text-[0.95rem] font-bold capitalize text-brand dark:text-white">{{ monthLabel }}</h2>
                        <Loader2 v-if="loading" class="size-4 animate-spin text-slate-400 dark:text-brand-gray" aria-label="Cargando eventos" />

                        <p v-if="connection.synced_at" class="ml-auto hidden text-[0.7rem] text-slate-400 @2xl:block dark:text-brand-gray/80">
                            Sincronizado {{ new Date(connection.synced_at).toLocaleString('es-MX', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) }}
                        </p>
                    </div>

                    <div class="flex flex-col border-t border-slate-100 md:min-h-0 md:flex-1 dark:border-white/[0.06]">
                        <!-- Encabezado de días, pegado al desplazar -->
                        <div
                            class="grid shrink-0 grid-cols-7 border-b border-slate-100 bg-slate-50 text-[0.62rem] font-bold uppercase tracking-[0.14em] text-slate-500 dark:border-white/[0.06] dark:bg-brand-deep dark:text-brand-gray"
                        >
                            <div v-for="day in WEEKDAYS" :key="day" class="px-2 py-2 text-center">{{ day }}</div>
                        </div>

                        <div class="grid grid-cols-7 md:min-h-0 md:flex-1" :style="weekRows">
                            <div
                                v-for="cell in days"
                                :key="cell.key"
                                class="flex min-h-20 flex-col overflow-hidden border-b border-r border-slate-100 p-1.5 transition-colors duration-150 last:border-r-0 md:min-h-0 dark:border-white/[0.06]"
                                :class="cell.outside ? 'bg-slate-50/60 dark:bg-white/[0.015]' : 'bg-white dark:bg-brand-deep'"
                            >
                                <!-- Hoy se marca con la pastilla de marca, no solo con color de texto -->
                                <span
                                    class="inline-grid size-6 place-content-center rounded-full text-[0.7rem] font-semibold tabular-nums"
                                    :class="[
                                        cell.today && 'bg-brand text-white shadow-sm shadow-brand/30 dark:bg-brand-light',
                                        !cell.today && cell.outside && 'text-slate-300 dark:text-white/25',
                                        !cell.today && !cell.outside && 'text-slate-600 dark:text-slate-300',
                                    ]"
                                >
                                    {{ cell.day }}
                                </span>

                                <ul class="mt-1 min-h-0 flex-1 space-y-0.5 overflow-y-auto">
                                    <li v-for="event in (byDay[cell.key] ?? []).slice(0, PER_CELL)" :key="event.id">
                                        <button type="button" :class="CHIP" :aria-label="`Ver ${event.title}`" @click.stop="show(event)">
                                            <span class="size-1.5 shrink-0 rounded-full bg-brand dark:bg-brand-gray" />
                                            <!-- Una sola línea: el título se corta y el detalle lo da el modal -->
                                            <span class="min-w-0 flex-1 truncate">
                                                <span class="tabular-nums text-slate-400 dark:text-brand-gray/80">{{ startLabel(event) }}</span>
                                                <span class="ml-1 font-medium text-slate-700 dark:text-slate-200">{{ event.title }}</span>
                                            </span>
                                        </button>
                                    </li>

                                    <li v-if="(byDay[cell.key] ?? []).length > PER_CELL">
                                        <button
                                            type="button"
                                            class="w-full cursor-pointer rounded-md px-1.5 py-0.5 text-left text-[0.68rem] font-semibold text-slate-500 transition-colors duration-150 hover:bg-slate-100 hover:text-brand focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/30 dark:text-brand-gray dark:hover:bg-white/[0.08] dark:hover:text-white"
                                            :class="popDay?.key === cell.key && 'bg-slate-100 text-brand dark:bg-white/[0.08] dark:text-white'"
                                            @click.stop="openDay(cell, $event)"
                                        >
                                            +{{ byDay[cell.key].length - PER_CELL }} más
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Todos los eventos del día -->
                <div
                    v-show="popDay"
                    ref="pop"
                    class="fixed z-50 w-68 overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-xl shadow-brand-deep/10 dark:border-white/10 dark:bg-brand-panel dark:shadow-black/40"
                    :style="popStyle"
                    @click.stop
                >
                    <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-3 py-2 dark:border-white/[0.06]">
                        <p class="truncate text-[0.72rem] font-bold capitalize text-slate-700 dark:text-white">
                            {{ popDay ? longDayLabel(popDay.key) : '' }}
                        </p>
                        <button
                            type="button"
                            class="grid size-6 cursor-pointer place-content-center rounded-md text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-white/10 dark:hover:text-white"
                            aria-label="Cerrar"
                            @click="closeDay"
                        >
                            <X class="size-3.5" />
                        </button>
                    </div>

                    <ul class="max-h-64 overflow-y-auto p-1.5">
                        <li v-for="event in popEvents" :key="event.id">
                            <button
                                type="button"
                                class="flex w-full cursor-pointer items-start gap-2 rounded-lg p-1.5 text-left transition-colors duration-150 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/30 dark:hover:bg-white/[0.06]"
                                @click="show(event)"
                            >
                                <span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-brand dark:bg-brand-gray" />
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-[0.75rem] font-semibold text-slate-800 dark:text-white">{{ event.title }}</span>
                                    <span class="block truncate text-[0.68rem] text-slate-500 dark:text-brand-gray">
                                        {{ timeLabel(event) }}<template v-if="event.location"> · {{ event.location }}</template>
                                    </span>
                                </span>
                                <span v-if="guests(event).length" class="shrink-0 text-[0.65rem] tabular-nums text-slate-400 dark:text-brand-gray/80">
                                    {{ guests(event).length }}
                                </span>
                            </button>
                        </li>
                    </ul>
                </div>

                <EventShowDialog v-model:open="detailOpen" :event="detail" />
            </template>
        </div>
    </AppShell>
</template>
