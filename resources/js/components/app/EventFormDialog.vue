<script setup>
import { useForm } from '@inertiajs/vue3';
import { CalendarPlus, Loader2, Save } from 'lucide-vue-next';
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

const editing = computed(() => form.event_id !== null);
const dateType = computed(() => (form.all_day ? 'date' : 'datetime-local'));

/**
 * Al cambiar el modo hay que recortar o reponer la hora: los inputs date y
 * datetime-local no aceptan el formato del otro. Va en el evento del checkbox
 * y no en un watch para que llenar el formulario no lo dispare.
 */
function toggleAllDay(allDay) {
    form.all_day = allDay;

    if (allDay) {
        form.starts_at = form.starts_at.slice(0, 10);
        form.ends_at = form.ends_at.slice(0, 10);
    } else {
        form.starts_at = `${form.starts_at.slice(0, 10)}T09:00`;
        form.ends_at = `${form.ends_at.slice(0, 10)}T10:00`;
    }
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
                        <Label for="starts_at">Inicio</Label>
                        <Input id="starts_at" v-model="form.starts_at" :type="dateType" required />
                        <InputError :message="form.errors.starts_at" />
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="ends_at">Fin</Label>
                        <Input id="ends_at" v-model="form.ends_at" :type="dateType" required />
                        <InputError :message="form.errors.ends_at" />
                    </div>
                </div>

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
