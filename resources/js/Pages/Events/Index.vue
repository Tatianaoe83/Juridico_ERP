<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    CalendarCheck,
    CalendarPlus,
    ExternalLink,
    Loader2,
    MapPin,
    Pencil,
    Save,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppShell from '@/Layouts/AppShell.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps({
    /** Hay cuenta de Microsoft vinculada. */
    connected: { type: Boolean, default: false },
    /** La vinculación incluye Calendars.ReadWrite. */
    canWrite: { type: Boolean, default: false },
    timezone: { type: String, default: 'UTC' },
    /** Próximos 30 días, tal como los devuelve Graph. */
    upcoming: { type: Array, default: () => [] },
    loadError: { type: String, default: null },
});

const breadcrumbs = [{ label: 'Inicio', href: '/calendario' }, { label: 'Eventos' }];

/* ---------- Formulario ---------- */

const pad = (n) => String(n).padStart(2, '0');

function toInput(date, withTime = true) {
    const day = `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;

    return withTime ? `${day}T${pad(date.getHours())}:${pad(date.getMinutes())}` : day;
}

/** Próxima hora en punto: el valor por defecto más útil al abrir la página. */
function nextHour() {
    const date = new Date();
    date.setMinutes(0, 0, 0);
    date.setHours(date.getHours() + 1);

    return date;
}

const start = nextHour();
const end = new Date(start.getTime() + 60 * 60 * 1000);

const form = useForm({
    /** null = alta · id de Graph = edición del evento existente. */
    event_id: null,
    title: '',
    all_day: false,
    starts_at: toInput(start),
    ends_at: toInput(end),
    location: '',
    description: '',
});

const editing = computed(() => form.event_id !== null);
const dateType = computed(() => (form.all_day ? 'date' : 'datetime-local'));

/**
 * Al cambiar el modo hay que recortar o reponer la hora: los inputs date y
 * datetime-local no aceptan el formato del otro. Va en el evento del checkbox
 * y no en un watch para que cargar un evento en el formulario no lo dispare.
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

function submit() {
    const options = { preserveScroll: true, onSuccess: () => reset() };

    editing.value ? form.patch('/eventos', options) : form.post('/eventos', options);
}

function reset() {
    form.reset();
    form.clearErrors();
}

/** Carga un evento existente en el formulario. */
function edit(event) {
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

    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function destroy(event) {
    if (!confirm(`¿Eliminar "${event.title}" de tu calendario de Outlook?`)) return;

    router.delete('/eventos', {
        data: { event_id: event.id },
        preserveScroll: true,
        onSuccess: () => {
            if (form.event_id === event.id) reset();
        },
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
</script>

<template>
    <Head title="Eventos" />

    <AppShell :breadcrumbs="breadcrumbs">
        <div class="mb-5 flex items-center gap-3">
            <span class="grid size-10 place-content-center rounded-lg bg-accent text-accent-foreground">
                <CalendarCheck class="size-5" />
            </span>
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Eventos</h1>
                <p class="text-sm text-muted-foreground">
                    Se crean directo en tu calendario de Outlook · {{ timezone }}
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

        <!-- Vinculada, pero sin permiso de escritura -->
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

        <div v-else class="grid gap-5 lg:grid-cols-3">
            <!-- Formulario -->
            <form class="rounded-xl border bg-card p-5 lg:col-span-2" @submit.prevent="submit">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="font-medium">{{ editing ? 'Editar evento' : 'Nuevo evento' }}</h2>
                    <Button v-if="editing" type="button" variant="ghost" size="sm" @click="reset">
                        <X class="size-4" />
                        Cancelar
                    </Button>
                </div>

                <div class="grid gap-4">
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
                        <Label for="location">Ubicación <span class="text-muted-foreground">(opcional)</span></Label>
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

                    <div class="flex items-center gap-3 pt-1">
                        <Button type="submit" :disabled="form.processing">
                            <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                            <Save v-else-if="editing" class="size-4" />
                            <CalendarPlus v-else class="size-4" />
                            {{ editing ? 'Guardar cambios' : 'Crear evento' }}
                        </Button>
                        <p class="text-xs text-muted-foreground">
                            Se aplica en Outlook al instante y te llega un correo.
                        </p>
                    </div>
                </div>
            </form>

            <!-- Próximos -->
            <section class="rounded-xl border bg-card p-5">
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
                        :class="form.event_id === event.id ? 'border-foreground' : 'border-[#459AF7]'"
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
        </div>
    </AppShell>
</template>
