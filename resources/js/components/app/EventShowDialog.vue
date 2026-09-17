<script setup>
import { AlignLeft, CalendarClock, CalendarDays, MapPin, UserRound, Users } from 'lucide-vue-next';
import { DialogClose } from 'reka-ui';
import { computed } from 'vue';
import AppModal from '@/components/app/AppModal.vue';
import { useEventDisplay } from '@/composables/useEventDisplay';

/**
 * Detalle de un evento del calendario, de solo lectura.
 *
 * La agenda vive en Outlook: aquí se consulta, no se edita. Por eso no hay
 * botones de guardar ni de eliminar, y el clic en un evento abre esto en vez
 * de sacar a la persona de la app.
 */
const props = defineProps({
    open: { type: Boolean, default: false },
    /** Evento tal como lo entrega Graph, o null mientras no hay ninguno abierto. */
    event: { type: Object, default: null },
});

const emit = defineEmits(['update:open']);

const { guests, response, longDayLabel, timeLabel } = useEventDisplay();

const invited = computed(() => (props.event ? guests(props.event) : []));

/** Cuántos contestaron qué: el resumen importa más que la lista cuando son muchos. */
const tally = computed(() =>
    invited.value.reduce((count, guest) => {
        const { label } = response(guest);
        count[label] = (count[label] ?? 0) + 1;

        return count;
    }, {}),
);

const SECTION = 'mb-2 flex items-center gap-2 text-[0.62rem] font-bold uppercase tracking-[0.16em] text-slate-400 dark:text-brand-gray/80';

const TILE = 'rounded-xl border border-slate-200/80 bg-slate-50/60 px-3.5 py-3 dark:border-white/[0.08] dark:bg-white/[0.03]';

const TILE_LABEL = 'flex items-center gap-2 text-[0.62rem] font-bold uppercase tracking-[0.14em] text-slate-400 dark:text-brand-gray/80';

const TILE_VALUE = 'mt-1.5 text-[0.85rem] font-bold text-slate-800 dark:text-white';
</script>

<template>
    <AppModal
        :open="open"
        size="md"
        :icon="CalendarDays"
        :title="event?.title ?? ''"
        :description="event ? longDayLabel(event.start) : ''"
        @update:open="emit('update:open', $event)"
    >
        <div v-if="event" class="space-y-5 px-6 pt-1 pb-6 short:space-y-4 short:pb-4 sm:px-7">
            <dl class="grid gap-2.5 sm:grid-cols-2">
                <div :class="TILE">
                    <dt :class="TILE_LABEL">
                        <CalendarClock class="size-3.5" />
                        Horario
                    </dt>
                    <dd :class="TILE_VALUE">{{ timeLabel(event) }}</dd>
                    <dd class="mt-0.5 text-[0.7rem] text-slate-500 capitalize dark:text-brand-gray">{{ longDayLabel(event.start) }}</dd>
                </div>

                <div :class="TILE">
                    <dt :class="TILE_LABEL">
                        <MapPin class="size-3.5" />
                        Lugar
                    </dt>
                    <dd :class="TILE_VALUE" :title="event.location ?? ''">{{ event.location || 'Sin ubicación' }}</dd>
                </div>

                <div :class="[TILE, 'sm:col-span-2']">
                    <dt :class="TILE_LABEL">
                        <UserRound class="size-3.5" />
                        Organiza
                    </dt>
                    <dd :class="TILE_VALUE">{{ event.organizer || '—' }}</dd>
                </div>
            </dl>

            <section v-if="event.description">
                <h3 :class="SECTION">
                    <AlignLeft class="size-3.5" />
                    Descripción
                    <span class="h-px flex-1 bg-slate-100 dark:bg-white/[0.06]" />
                </h3>
                <!-- whitespace-pre-line: respeta los saltos con los que se escribió en Outlook -->
                <p class="text-[0.8rem] leading-relaxed whitespace-pre-line text-slate-700 dark:text-slate-300">{{ event.description }}</p>
            </section>

            <section v-if="invited.length">
                <h3 :class="SECTION">
                    <Users class="size-3.5" />
                    Invitados
                    <span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[0.65rem] tabular-nums dark:bg-white/10">{{ invited.length }}</span>
                    <span class="h-px flex-1 bg-slate-100 dark:bg-white/[0.06]" />
                </h3>

                <!-- El resumen primero: con doce invitados, lo que se busca es cuántos aceptaron -->
                <p class="mb-2 flex flex-wrap gap-x-3 gap-y-1 text-[0.7rem] text-slate-500 dark:text-brand-gray">
                    <span v-for="(count, label) in tally" :key="label">
                        <span class="font-bold text-slate-700 tabular-nums dark:text-white">{{ count }}</span>
                        {{ label.toLowerCase() }}
                    </span>
                </p>

                <ul class="max-h-48 space-y-1 overflow-y-auto">
                    <li
                        v-for="guest in invited"
                        :key="guest.email"
                        class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-[0.78rem] odd:bg-slate-50/70 dark:odd:bg-white/[0.03]"
                    >
                        <span class="size-1.5 shrink-0 rounded-full" :class="response(guest).dot" />
                        <span class="min-w-0 flex-1 truncate text-slate-700 dark:text-slate-200" :title="guest.email">
                            {{ guest.name || guest.email }}
                        </span>
                        <!-- El texto acompaña al punto: el color solo no dice nada a quien no lo distingue -->
                        <span class="shrink-0 text-[0.68rem] text-slate-400 dark:text-brand-gray/80">{{ response(guest).label }}</span>
                    </li>
                </ul>
            </section>
        </div>

        <template #footer>
            <DialogClose
                class="h-9 cursor-pointer rounded-xl border border-slate-200 bg-white px-4 text-[0.8rem] font-semibold text-slate-700 transition-colors duration-150 hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-500/10 dark:border-white/10 dark:bg-transparent dark:text-brand-gray dark:hover:bg-white/[0.06] dark:hover:text-white"
            >
                Cerrar
            </DialogClose>
        </template>
    </AppModal>
</template>
