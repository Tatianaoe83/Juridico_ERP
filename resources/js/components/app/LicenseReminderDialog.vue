<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { BellOff, BellRing, CalendarClock, Check, Info, Loader2, Lock, Users } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import AppModal from '@/components/app/AppModal.vue';
import { localDate, reminderOptions } from '@/lib/licenses';

const props = defineProps({
    open: { type: Boolean, default: false },
    /** Fila de la tabla, con `notification` (null si aún no tiene aviso). */
    license: { type: Object, default: null },
    /** Correos con acceso al calendario: son los que reciben la invitación. */
    sharedWith: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:open']);

// Una sola decisión: cuándo avisar. Esos minutos viajan al evento y Outlook
// se encarga de lanzar la alerta.
const form = useForm({ minutes_before: null });

const existing = computed(() => props.license?.notification ?? null);

/**
 * Los presets cambian con la licencia: con hora se cuentan desde ella; sin
 * hora el evento arranca a medianoche y Outlook cuenta desde ahí, por eso
 * «el día anterior a las 9:00» son 900 minutos.
 */
const hasTime = computed(() => Boolean(props.license?.valid_time));

const options = computed(() => reminderOptions(hasTime.value));

// Cada apertura carga lo guardado; sin aviso previo, ninguno seleccionado.
watch(
    () => props.open,
    (open) => {
        if (!open) return;

        form.defaults({ minutes_before: existing.value?.minutes_before ?? null });
        form.reset();
        form.clearErrors();
        confirmRemove.value = false;
    },
    { immediate: true },
);

function close() {
    emit('update:open', false);
}

function submit() {
    if (form.minutes_before === null) return;

    form.put(`/licencias/${props.license.id}/recordatorio`, { preserveScroll: true, onSuccess: close });
}

/* ---------- Cuándo cae cada opción ---------- */

/** Inicio del evento: la vigencia con su hora, o medianoche si es de todo el día. */
const startsAt = computed(() => {
    if (!props.license?.valid_until) return null;

    const day = localDate(props.license.valid_until);

    if (props.license.valid_time) {
        const [hour, minute] = props.license.valid_time.split(':').map(Number);
        day.setHours(hour, minute);
    }

    return day;
});

/** Fecha y hora exactas de un aviso, para que no haya que calcular minutos de cabeza. */
function momentFor(minutes) {
    if (!startsAt.value) return null;

    return new Date(startsAt.value.getTime() - minutes * 60_000);
}

function longMoment(date) {
    return date.toLocaleString('es-MX', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function shortMoment(minutes) {
    const moment = momentFor(minutes);

    return moment ? moment.toLocaleString('es-MX', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) : '';
}

/** Resumen de lo elegido: lo que de verdad va a pasar, en una línea. */
const summary = computed(() => {
    if (form.minutes_before === null) return 'Elige cuándo quieres el aviso.';
    if (!startsAt.value) return 'Se programará en cuanto la licencia tenga vigencia.';

    const moment = momentFor(form.minutes_before);

    return `${moment < new Date() ? 'Tocaba el' : 'Avisa el'} ${longMoment(moment)}`;
});

/* ---------- Quitar ---------- */

// Dos clics y no un segundo modal: apilar diálogos se pelea por el foco.
const confirmRemove = ref(false);
const removing = ref(false);

function remove() {
    if (!confirmRemove.value) {
        confirmRemove.value = true;

        return;
    }

    removing.value = true;

    router.delete(`/licencias/${props.license.id}/recordatorio`, {
        preserveScroll: true,
        onSuccess: close,
        onFinish: () => (removing.value = false),
    });
}

/* ---------- Medidas ---------- */

// Tarjeta seleccionable: alto cómodo para el dedo y anillo de foco propio,
// porque el radio de verdad va oculto.
const CARD =
    'group relative flex min-h-11 cursor-pointer items-center gap-2.5 rounded-xl border px-3 py-2.5 text-left transition-colors duration-150 ' +
    'has-[:focus-visible]:ring-4 has-[:focus-visible]:ring-brand/20 dark:has-[:focus-visible]:ring-white/15';

const CARD_OFF =
    'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50 ' +
    'dark:border-white/10 dark:bg-white/[0.03] dark:hover:border-white/20 dark:hover:bg-white/[0.06]';

const CARD_ON =
    'border-brand bg-brand/[0.06] dark:border-brand-gray/60 dark:bg-white/[0.10]';

const SECTION = 'mb-2.5 flex items-center gap-2 text-[0.62rem] font-bold uppercase tracking-[0.16em] text-slate-400 dark:text-brand-gray/80';
</script>

<template>
    <AppModal
        :open="open"
        size="lg"
        :icon="BellRing"
        title="Aviso de vencimiento"
        :description="license ? `Cuándo avisa Outlook de ${license.name} y a quién le llega.` : ''"
        @update:open="emit('update:open', $event)"
    >
        <div v-if="license" class="space-y-5 px-6 pt-1 pb-6 short:space-y-4 short:pb-4 sm:px-7">
            <!-- Sin vigencia no hay desde dónde contar: se guarda, pero no sale nada aún -->
            <div
                v-if="!license.valid_until"
                class="flex gap-2.5 rounded-xl border border-amber-300/60 bg-amber-50 px-3.5 py-3 dark:border-amber-400/25 dark:bg-amber-400/10"
            >
                <Info class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-300" />
                <p class="text-xs leading-relaxed text-amber-800 dark:text-amber-200/90">
                    Este registro sigue en trámite y no tiene vigencia. Puedes dejar el aviso listo: empezará a contar cuando le pongas la fecha.
                </p>
            </div>

            <!-- Cuándo -->
            <section>
                <h3 :class="SECTION">
                    <CalendarClock class="size-3.5" />
                    Cuándo avisar
                    <span class="h-px flex-1 bg-slate-100 dark:bg-white/[0.06]" />
                </h3>

                <form id="reminder-form" novalidate @submit.prevent="submit">
                    <div class="grid gap-2 sm:grid-cols-2" role="radiogroup" aria-label="Cuándo avisar">
                        <label
                            v-for="option in options"
                            :key="option.value"
                            :class="[CARD, form.minutes_before === option.value ? CARD_ON : CARD_OFF]"
                        >
                            <input
                                v-model="form.minutes_before"
                                type="radio"
                                name="minutes_before"
                                :value="option.value"
                                class="sr-only"
                            />
                            <!-- La palomita, y no solo el color, marca lo elegido -->
                            <span
                                class="grid size-5 shrink-0 place-content-center rounded-full border transition-colors duration-150"
                                :class="
                                    form.minutes_before === option.value
                                        ? 'border-brand bg-brand text-white dark:border-brand-gray dark:bg-brand-gray dark:text-brand'
                                        : 'border-slate-300 text-transparent dark:border-white/25'
                                "
                            >
                                <Check class="size-3" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span
                                    class="block truncate text-[0.8rem] text-slate-700 dark:text-slate-200"
                                    :class="form.minutes_before === option.value ? 'font-bold text-brand dark:text-white' : 'font-medium'"
                                >
                                    {{ option.label }}
                                </span>
                                <span v-if="startsAt" class="block truncate text-[0.68rem] tabular-nums text-slate-400 dark:text-brand-gray/80">
                                    {{ shortMoment(option.value) }}
                                </span>
                            </span>
                        </label>
                    </div>
                </form>

                <p
                    class="mt-2.5 flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2 text-[0.72rem] text-slate-600 dark:bg-white/[0.04] dark:text-brand-gray"
                    aria-live="polite"
                >
                    <BellRing v-if="form.minutes_before !== null" class="size-3.5 shrink-0 text-brand dark:text-white" />
                    <BellOff v-else class="size-3.5 shrink-0" />
                    <span class="first-letter:uppercase">{{ summary }}</span>
                </p>

                <p v-if="form.errors.minutes_before" class="mt-1 text-[0.7rem] font-medium text-red-600 dark:text-red-400">
                    {{ form.errors.minutes_before }}
                </p>
            </section>

            <!-- Quién -->
            <section>
                <h3 :class="SECTION">
                    <Users class="size-3.5" />
                    Quién lo recibe
                    <span class="h-px flex-1 bg-slate-100 dark:bg-white/[0.06]" />
                </h3>

                <div class="rounded-xl border border-slate-200/80 bg-slate-50/60 p-3 dark:border-white/[0.08] dark:bg-white/[0.03]">
                    <ul v-if="sharedWith.length" class="flex flex-wrap gap-1.5">
                        <li
                            v-for="email in sharedWith"
                            :key="email"
                            class="inline-flex max-w-full items-center gap-2 rounded-lg bg-white py-1 pr-2.5 pl-1 text-[0.75rem] font-medium text-slate-700 ring-1 ring-inset ring-slate-200 dark:bg-white/[0.06] dark:text-white dark:ring-white/10"
                        >
                            <span
                                class="grid size-5 shrink-0 place-content-center rounded-md bg-brand/[0.08] text-[0.6rem] font-bold text-brand uppercase dark:bg-white/10 dark:text-white"
                                aria-hidden="true"
                            >
                                {{ email[0] }}
                            </span>
                            <span class="truncate">{{ email }}</span>
                        </li>
                    </ul>

                    <p v-else class="text-[0.75rem] text-slate-500 dark:text-brand-gray">
                        El calendario no está compartido con nadie: nadie recibe la invitación ni la alerta.
                    </p>

                    <!-- Solo lectura, no deshabilitado: se cambia, pero en otra pantalla -->
                    <p class="mt-2.5 flex items-start gap-1.5 text-[0.68rem] leading-relaxed text-slate-400 dark:text-brand-gray/80">
                        <Lock class="mt-0.5 size-3 shrink-0" />
                        <span>
                            Son los correos con acceso al calendario: reciben la invitación del evento y, con ella, la alerta de Outlook a la hora elegida.
                            Se dan de alta en <span class="font-semibold text-slate-500 dark:text-brand-gray">Eventos → Compartir calendario</span>.
                        </span>
                    </p>
                </div>
            </section>
        </div>

        <template #footer>
            <button
                v-if="existing"
                type="button"
                class="mr-auto inline-flex h-9 cursor-pointer items-center justify-center gap-1.5 rounded-xl px-3 text-[0.8rem] font-semibold transition-colors duration-150 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-500/15 disabled:pointer-events-none disabled:opacity-60"
                :class="confirmRemove ? 'bg-red-600 text-white hover:bg-red-700 dark:bg-red-500' : 'text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10'"
                :disabled="removing"
                @click="remove"
            >
                <Loader2 v-if="removing" class="size-4 animate-spin" />
                <BellOff v-else class="size-4" />
                {{ confirmRemove ? '¿Seguro? Quitar' : 'Quitar aviso' }}
            </button>
            <button
                type="button"
                class="h-9 cursor-pointer rounded-xl border border-slate-200 bg-white px-4 text-[0.8rem] font-semibold text-slate-700 transition-colors duration-150 hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-500/10 dark:border-white/10 dark:bg-transparent dark:text-brand-gray dark:hover:bg-white/[0.06] dark:hover:text-white"
                @click="close"
            >
                Cancelar
            </button>
            <button
                type="submit"
                form="reminder-form"
                class="inline-flex h-9 cursor-pointer items-center justify-center gap-2 rounded-xl bg-brand px-4 text-[0.8rem] font-semibold text-white shadow-md shadow-brand/25 transition-all duration-150 hover:bg-brand/90 hover:shadow-lg hover:shadow-brand/30 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/25 active:translate-y-px disabled:pointer-events-none disabled:opacity-60 dark:bg-brand-light dark:shadow-black/30 dark:hover:bg-brand-light/90"
                :disabled="form.processing || form.minutes_before === null"
            >
                <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                <BellRing v-else class="size-4" />
                Guardar aviso
            </button>
        </template>
    </AppModal>
</template>
