<script setup>
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    ExternalLink,
    Loader2,
    RefreshCw,
    Unlink,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import AppShell from '@/Layouts/AppShell.vue';
import { Button } from '@/components/ui/button';

const props = defineProps({
    /** null cuando el usuario aún no vincula su cuenta de Microsoft. */
    connection: { type: Object, default: null },
    /** 'delegated' = cada quien conecta la suya · 'application' = la app lee el buzón */
    mode: { type: String, default: 'delegated' },
    timezone: { type: String, default: 'UTC' },
});

const breadcrumbs = [{ label: 'Inicio', href: '/calendario' }, { label: 'Calendario' }];

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

function hour(event) {
    return event.all_day ? 'Todo el día' : (event.start ?? '').slice(11, 16);
}

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

function disconnect() {
    if (confirm('¿Desvincular tu cuenta de Outlook? Se borran los tokens guardados.')) {
        router.delete('/auth/microsoft');
    }
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
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <span class="grid size-10 place-content-center rounded-lg bg-accent text-accent-foreground">
                    <CalendarDays class="size-5" />
                </span>
                <div>

                    <h1 class="text-2xl font-semibold tracking-tight">Calendario</h1>
                    <p class="text-sm font-medium mt-4 ">Correos Registrados:</p>
                    <p class="text-sm text-muted-foreground">
                        <template v-if="connection">{{ connection.email }} </template>
                        <template class="mb-4" v-else>Conecta tu cuenta para ver tu agenda</template>
                    </p>
                    <P class="text-sm font-medium mt-4" >Zona horaria:</P>
                    <p class="text-sm text-muted-foreground" > {{ timezone }}</p>
                </div>
            </div>

            <div v-if="connection" class="flex items-center gap-2">
                <Button variant="outline" size="sm" :disabled="loading" @click="load(true)">
                    <RefreshCw class="size-4" :class="loading && 'animate-spin'" />
                    Actualizar
                </Button>
                <Button v-if="mode === 'delegated'" variant="outline" size="sm" @click="disconnect">
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
                        class="min-h-28 border-b border-r p-1.5"
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
                            <li v-for="event in (byDay[cell.key] ?? []).slice(0, 3)" :key="event.id">
                                <a
                                    :href="event.url"
                                    target="_blank"
                                    rel="noopener"
                                    class="group flex items-start gap-1 rounded px-1 py-0.5 text-[0.7rem] leading-tight hover:bg-accent"
                                    :title="`${hour(event)} · ${event.title}${event.location ? ' · ' + event.location : ''}`"
                                >
                                    <span class="mt-1 size-1.5 shrink-0 rounded-full bg-[#459AF7]" />
                                    <span class="min-w-0">
                                        <span class="text-muted-foreground">{{ hour(event) }}</span>
                                        <span class="ml-1 line-clamp-2">{{ event.title }}</span>
                                    </span>
                                    <ExternalLink class="mt-0.5 size-3 shrink-0 opacity-0 group-hover:opacity-60" />
                                </a>
                            </li>
                            <li
                                v-if="(byDay[cell.key] ?? []).length > 3"
                                class="px-1 text-[0.7rem] text-muted-foreground"
                            >
                                +{{ byDay[cell.key].length - 3 }} más
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <p v-if="connection.synced_at" class="mt-3 text-xs text-muted-foreground">
                Última sincronización: {{ new Date(connection.synced_at).toLocaleString('es-MX') }}
            </p>
        </template>
    </AppShell>
</template>
