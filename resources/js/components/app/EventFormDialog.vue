<script setup>
import { useForm } from '@inertiajs/vue3';
import { CalendarPlus, ChevronDown, Loader2, Save } from 'lucide-vue-next';
import { computed, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps({
    open: { type: Boolean, default: false },
    /** null = alta · evento de Graph = edición. */
    event: { type: Object, default: null },
    /**
     * Dueño del calendario sobre el que se actúa. Sin esto el servidor
     * resolvería el primero de la lista y el evento caería en otro calendario.
     */
    calendar: { type: Number, default: null },
});

const emit = defineEmits(['update:open', 'saved']);

/* ---------- Valores por defecto ---------- */

const pad = (n) => String(n).padStart(2, '0');

function toInput(date, withTime = true) {
    const day = `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;

    return withTime ? `${day}T${pad(date.getHours())}:${pad(date.getMinutes())}` : day;
}

/** Próxima hora en punto: el valor más útil al abrir el diálogo en blanco. */
function nextHour() {
    const date = new Date();
    date.setMinutes(0, 0, 0);
    date.setHours(date.getHours() + 1);

    return date;
}

const form = useForm({
    event_id: null,
    title: '',
    all_day: false,
    starts_at: '',
    ends_at: '',
    location: '',
    description: '',
    /**
     * Sin campo visible, pero viaja igual: en una edición Graph reemplaza la
     * lista completa, así que mandarla vacía desinvitaría a todos.
     */
    attendees: [],
});

/*
 * `invite_shared` no viaja desde aquí a propósito. El servidor lo trata como
 * activo cuando falta, así que a los del calendario compartido siempre se les
 * invita y reciben los avisos nativos de Outlook. Si algún día hace falta
 * poder apagarlo por evento, basta con reponer la casilla: el backend ya lo
 * acepta.
 */

const editing = computed(() => form.event_id !== null);

/* ---------- Fecha y hora ---------- */

/**
 * La fecha usa el input nativo, que funciona bien. La hora no: el selector de
 * `datetime-local` es una columna diminuta con desplazamiento, distinta en cada
 * navegador. Se sustituye por un desplegable de intervalos de 15 minutos.
 *
 * Como el input siempre es `type="date"`, tampoco puede repetirse el fallo de
 * quedarse en blanco al cambiar de modo.
 */
const STEP_MINUTES = 15;

/**
 * Mismas medidas y colores que el componente Input, para que no desentone.
 *
 * Dos cosas que impone @tailwindcss/forms y hay que deshacer:
 *
 *  - Dibuja su flecha con `background-image`, que `appearance-none` no quita:
 *    sin `bg-none` se encima con el chevron de Lucide y se ve como un garabato.
 *  - Pone 0.5rem de relleno vertical con `line-height: 1.5rem`, o sea 40px de
 *    contenido dentro de una caja de 32px, y el texto queda descuadrado. `py-1`
 *    lo devuelve a 32px exactos, igual que hace el componente Input.
 */
const TIME_SELECT =
    'h-8 w-[5.25rem] appearance-none bg-none rounded-lg border border-input bg-transparent ' +
    'px-2.5 py-1 text-base tabular-nums text-foreground outline-none transition-colors ' +
    'focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 md:text-sm dark:bg-input/30';

const pad2 = (n) => String(n).padStart(2, '0');

const dayOf = (value) => value.slice(0, 10);
const timeOf = (value) => value.slice(11, 16);

function combine(day, time) {
    return form.all_day || !time ? day : `${day}T${time}`;
}

const startsOn = computed({
    get: () => dayOf(form.starts_at),
    set: (day) => (form.starts_at = combine(day, timeOf(form.starts_at) || '09:00')),
});

const endsOn = computed({
    get: () => dayOf(form.ends_at),
    set: (day) => (form.ends_at = combine(day, timeOf(form.ends_at) || '10:00')),
});

const startsAtTime = computed({
    get: () => timeOf(form.starts_at) || '09:00',
    set: (time) => {
        form.starts_at = combine(dayOf(form.starts_at), time);
        keepDuration();
    },
});

const endsAtTime = computed({
    get: () => timeOf(form.ends_at) || '10:00',
    set: (time) => (form.ends_at = combine(dayOf(form.ends_at), time)),
});

/** Fecha real a partir de 'YYYY-MM-DDTHH:mm'; los cortes evitan zonas horarias. */
function toDate(value) {
    const [y, m, d] = dayOf(value).split('-').map(Number);
    const [hh, mm] = (timeOf(value) || '00:00').split(':').map(Number);

    return y ? new Date(y, m - 1, d, hh, mm) : null;
}

/** Mover el inicio a después del fin dejaría el formulario inválido en silencio. */
function keepDuration() {
    if (form.all_day) return;

    const start = toDate(form.starts_at);
    const end = toDate(form.ends_at);

    if (!start || !end || end > start) return;

    const shifted = new Date(start.getTime() + 60 * 60 * 1000);

    form.ends_at = `${shifted.getFullYear()}-${pad2(shifted.getMonth() + 1)}-${pad2(shifted.getDate())}T${pad2(shifted.getHours())}:${pad2(shifted.getMinutes())}`;
}

/** 00:00 a 23:45 cada cuarto de hora. */
const TIMES = Array.from({ length: (24 * 60) / STEP_MINUTES }, (_, i) => {
    const total = i * STEP_MINUTES;

    return `${pad2(Math.floor(total / 60))}:${pad2(total % 60)}`;
});

/**
 * Outlook devuelve horas fuera de la retícula —un evento a las 11:07 existe—,
 * así que se añaden para no perderlas al abrir el formulario.
 */
const timeOptions = computed(() =>
    [...new Set([...TIMES, startsAtTime.value, endsAtTime.value])].sort(),
);

const duration = computed(() => {
    const start = toDate(form.starts_at);
    const end = toDate(form.ends_at);

    if (!start || !end) return '';

    if (form.all_day) {
        const days = Math.round((end - start) / 86400000) + 1;

        return days === 1 ? 'Un día completo' : `${days} días completos`;
    }

    const minutes = Math.round((end - start) / 60000);

    if (minutes <= 0) return 'El fin debe ser posterior al inicio';

    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    return [hours ? `${hours} h` : null, rest ? `${rest} min` : null].filter(Boolean).join(' ');
});

/**
 * Marcar «todo el día» descarta la hora; desmarcarlo repone una razonable.
 * Va en el evento del checkbox y no en un watch para que llenar el formulario
 * con un evento existente no lo dispare.
 */
function toggleAllDay(allDay) {
    const from = dayOf(form.starts_at);
    const to = dayOf(form.ends_at);

    form.all_day = allDay;
    form.starts_at = allDay ? from : `${from}T09:00`;
    form.ends_at = allDay ? to : `${to}T10:00`;
}

function blank() {
    const start = nextHour();
    const end = new Date(start.getTime() + 60 * 60 * 1000);

    form.defaults({
        event_id: null,
        title: '',
        all_day: false,
        starts_at: toInput(start),
        ends_at: toInput(end),
        location: '',
        description: '',
        attendees: [],
    });

    form.reset();
    form.clearErrors();
}

/** Carga un evento de Graph en el formulario. */
function fill(event) {
    const allDay = Boolean(event.all_day);
    // Graph devuelve '2026-07-31T14:00:00.0000000'; los inputs quieren
    // 'YYYY-MM-DD' o 'YYYY-MM-DDTHH:mm'.
    const trim = (iso) => (allDay ? (iso ?? '').slice(0, 10) : (iso ?? '').slice(0, 16));

    // En todo el día Graph guarda el fin exclusivo (día siguiente); se resta
    // para que el formulario muestre el último día real.
    let ends = trim(event.end);

    if (allDay && ends) {
        const date = new Date(`${ends}T00:00:00`);
        date.setDate(date.getDate() - 1);
        ends = toInput(date, false);
    }

    form.clearErrors();
    form.event_id = event.id;
    form.title = event.title;
    form.all_day = allDay;
    form.starts_at = trim(event.start);
    form.ends_at = ends;
    form.location = event.location ?? '';
    form.description = event.description ?? '';
    // El organizador viene en la lista de Graph, pero no se invita a sí mismo.
    form.attendees = (event.attendees ?? [])
        .filter((attendee) => attendee.response !== 'organizer')
        .map((attendee) => attendee.email);
}

// Cada apertura parte de cero y, si hay evento, lo carga encima.
watch(
    () => props.open,
    (open) => {
        if (!open) return;

        blank();

        if (props.event) fill(props.event);
    },
    { immediate: true },
);

function close() {
    emit('update:open', false);
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            close();
            emit('saved');
        },
    };

    form.transform((data) => ({
        ...data,
        ...(props.calendar ? { calendario: props.calendar } : {}),
    }));

    editing.value ? form.patch('/eventos', options) : form.post('/eventos', options);
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ editing ? 'Editar evento' : 'Nuevo evento' }}</DialogTitle>
                <DialogDescription>
                    Se aplica en Outlook al instante y te llega un correo.
                </DialogDescription>
            </DialogHeader>

            <form id="event-form" class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-1.5">
                    <Label for="title">Título</Label>
                    <Input id="title" v-model="form.title" required autofocus maxlength="255" />
                    <InputError :message="form.errors.title" />
                </div>

                <label class="flex w-fit items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        class="size-4 rounded border-input accent-[#459AF7]"
                        :checked="form.all_day"
                        @change="toggleAllDay($event.target.checked)"
                    />
                    Todo el día
                </label>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="starts_on">Inicio</Label>
                        <div class="flex gap-2">
                            <Input id="starts_on" v-model="startsOn" type="date" required class="flex-1" />
                            <!-- `appearance-none` mata la flecha del plugin de forms, que trae
                                 su propio color y espaciado y desentona con el resto -->
                            <div v-if="!form.all_day" class="relative shrink-0">
                                <select
                                    v-model="startsAtTime"
                                    aria-label="Hora de inicio"
                                    :class="TIME_SELECT"
                                >
                                    <option
                                        v-for="time in timeOptions"
                                        :key="time"
                                        :value="time"
                                        class="bg-popover text-popover-foreground"
                                    >
                                        {{ time }}
                                    </option>
                                </select>
                                <ChevronDown
                                    class="pointer-events-none absolute right-2 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground"
                                />
                            </div>
                        </div>
                        <InputError :message="form.errors.starts_at" />
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="ends_on">Fin</Label>
                        <div class="flex gap-2">
                            <Input id="ends_on" v-model="endsOn" type="date" required class="flex-1" />
                            <div v-if="!form.all_day" class="relative shrink-0">
                                <select v-model="endsAtTime" aria-label="Hora de fin" :class="TIME_SELECT">
                                    <option
                                        v-for="time in timeOptions"
                                        :key="time"
                                        :value="time"
                                        class="bg-popover text-popover-foreground"
                                    >
                                        {{ time }}
                                    </option>
                                </select>
                                <ChevronDown
                                    class="pointer-events-none absolute right-2 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground"
                                />
                            </div>
                        </div>
                        <InputError :message="form.errors.ends_at" />
                    </div>
                </div>

                <p v-if="duration" class="-mt-2 text-xs text-muted-foreground">
                    Duración: {{ duration }}
                </p>

                <div class="grid gap-1.5">
                    <Label for="location">
                        Ubicación <span class="text-muted-foreground">(opcional)</span>
                    </Label>
                    <Input id="location" v-model="form.location" maxlength="255" placeholder="Sala de juntas" />
                    <InputError :message="form.errors.location" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="description">
                        Descripción <span class="text-muted-foreground">(opcional)</span>
                    </Label>
                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="3"
                        maxlength="2000"
                        class="w-full rounded-lg border border-input bg-transparent px-2.5 py-1.5 text-sm outline-none transition-colors focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 dark:bg-input/30"
                    />
                    <InputError :message="form.errors.description" />
                </div>

            </form>

            <DialogFooter>
                <Button type="button" variant="outline" @click="close">Cancelar</Button>
                <Button type="submit" form="event-form" :disabled="form.processing">
                    <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                    <Save v-else-if="editing" class="size-4" />
                    <CalendarPlus v-else class="size-4" />
                    {{ editing ? 'Guardar cambios' : 'Crear evento' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
