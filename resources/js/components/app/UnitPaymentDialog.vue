<script setup>
import { useForm } from '@inertiajs/vue3';
import { BadgeCheck, CalendarDays, CircleDollarSign, FileText, Loader2, Paperclip, RefreshCw, Wallet, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import AppModal from '@/components/app/AppModal.vue';
import { SEMESTER_MONTHS, addMonths, money, periodLabel } from '@/lib/units';

/**
 * Registrar un pago semestral: el comprobante y el día en que se pagó. No es
 * un abono, no lleva monto: vale el importe del periodo.
 *
 * El segundo pago cierra el periodo y abre el siguiente, así que además pide
 * la póliza nueva —cambia en cada renovación— y los importes que siguen.
 */
const props = defineProps({
    open: { type: Boolean, default: false },
    unitId: { type: Number, required: true },
    /** El periodo vigente: { id, policy, certificate, first_payment, second_payment }. */
    policy: { type: Object, default: null },
    /** Los pagos que se pueden registrar ('first' | 'second'), en orden. */
    payable: { type: Array, default: () => ['first'] },
});

const emit = defineEmits(['update:open']);

/** El que se va a registrar. Si los dos están pendientes, se elige aquí. */
const payment = ref('first');

const renews = computed(() => payment.value === 'second');

const current = computed(() => props.policy?.[`${payment.value}_payment`] ?? null);

/** Hoy en la zona del navegador, como lo espera un input date. */
function today() {
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

const form = useForm({
    receipt: null,
    paid_at: today(),
    new_policy: '',
    new_certificate: '',
    new_first_payment_amount: '',
    new_second_payment_amount: '',
});

const fileInput = ref(null);

// Cada apertura arranca limpia; los importes nuevos parten de los actuales,
// que casi siempre se repiten.
watch(
    () => props.open,
    (open) => {
        if (!open) return;

        payment.value = props.payable[0] ?? 'first';

        form.defaults({
            receipt: null,
            paid_at: today(),
            new_policy: '',
            new_certificate: props.policy?.certificate ?? '',
            new_first_payment_amount: props.policy?.first_payment?.amount || '',
            new_second_payment_amount: props.policy?.second_payment?.amount || '',
        });
        form.reset();
        form.clearErrors();
    },
    { immediate: true },
);

/**
 * El periodo que se va a crear, tal como lo calcula el servidor: arranca
 * cuando cierra el segundo pago, con dos semestres de seis meses exactos.
 */
const next = computed(() => {
    const start = props.policy?.second_payment?.ends_on;

    if (!renews.value || !start) return null;

    const middle = addMonths(start, SEMESTER_MONTHS);

    return {
        first: { starts_on: start, ends_on: middle },
        second: { starts_on: middle, ends_on: addMonths(middle, SEMESTER_MONTHS) },
    };
});

/** Cambiar de pago descarta los errores del otro. */
function choose(key) {
    payment.value = key;
    form.clearErrors();
}

function pickFile(event) {
    form.receipt = event.target.files[0] ?? null;
    event.target.value = '';
}

function close() {
    emit('update:open', false);
}

function submit() {
    // Solo viaja lo que aplica: el primer pago no renueva.
    form.transform((data) => (renews.value ? data : { receipt: data.receipt, paid_at: data.paid_at })).post(
        `/flotillas/${props.unitId}/periodos/${props.policy.id}/pagos/${payment.value}`,
        { preserveScroll: true, forceFormData: true, onSuccess: close },
    );
}

const FIELD =
    'peer h-9 w-full rounded-lg border border-slate-200 bg-slate-50/70 pr-3 pl-9 text-[0.8rem] text-slate-900 outline-none ' +
    'transition-[border-color,background-color,box-shadow] duration-150 placeholder:text-slate-400 ' +
    'hover:border-slate-300 focus:border-brand/50 focus:bg-white focus:ring-4 focus:ring-brand/10 ' +
    'aria-invalid:border-red-400 aria-invalid:focus:ring-red-500/10 ' +
    'dark:border-white/10 dark:bg-white/[0.04] dark:text-white dark:placeholder:text-white/30 dark:hover:border-white/20 ' +
    'dark:focus:border-brand-gray/50 dark:focus:bg-white/[0.06] dark:focus:ring-white/10 dark:[color-scheme:dark]';

const ICON =
    'pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-slate-400 transition-colors ' +
    'peer-focus:text-brand dark:text-white/35 dark:peer-focus:text-white';

const LABEL = 'mb-1.5 block text-[0.75rem] font-semibold text-slate-700 dark:text-slate-200';

const ERROR = 'mt-1 text-[0.7rem] font-medium text-red-600 dark:text-red-400';

const REQUIRED = 'font-normal text-red-500 dark:text-red-400';
</script>

<template>
    <AppModal
        :open="open"
        size="lg"
        :icon="Wallet"
        :title="renews ? 'Registrar segundo pago y renovar' : 'Registrar primer pago'"
        :description="
            renews
                ? `Marca como pagado el segundo semestre de la póliza ${policy?.policy}. Con esto se crea el periodo siguiente.`
                : `Marca como pagado el primer semestre de la póliza ${policy?.policy}.`
        "
        @update:open="emit('update:open', $event)"
    >
        <form id="unit-payment-form" class="flex flex-col gap-4 px-6 pt-1 pb-6 short:pb-4 sm:px-7" novalidate @submit.prevent="submit">
            <!-- Los dos pendientes: se elige cuál; se puede pagar el segundo antes -->
            <div v-if="payable.length > 1" class="grid grid-cols-2 gap-1 rounded-xl bg-slate-100 p-1 dark:bg-white/[0.05]" role="radiogroup" aria-label="Pago a registrar">
                <button
                    v-for="key in payable"
                    :key="key"
                    type="button"
                    role="radio"
                    :aria-checked="payment === key"
                    class="h-8 cursor-pointer rounded-lg text-[0.75rem] font-semibold transition-colors duration-150 focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-brand/25"
                    :class="
                        payment === key
                            ? 'bg-white text-brand shadow-sm dark:bg-white/15 dark:text-white'
                            : 'text-slate-500 hover:text-slate-800 dark:text-brand-gray dark:hover:text-white'
                    "
                    @click="choose(key)"
                >
                    {{ key === 'first' ? 'Primer pago' : 'Segundo pago' }}
                </button>
            </div>

            <!-- Qué se está pagando: el semestre con su periodo e importe -->
            <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-slate-50/70 px-3 py-2.5 ring-1 ring-inset ring-slate-200/70 dark:bg-white/[0.03] dark:ring-white/[0.06]">
                <div class="min-w-0">
                    <p class="text-[0.62rem] font-bold uppercase tracking-[0.14em] text-slate-400 dark:text-brand-gray/80">
                        {{ renews ? 'Segundo pago' : 'Primer pago' }}
                    </p>
                    <p class="text-[0.78rem] text-slate-600 dark:text-slate-300">{{ periodLabel(current) }}</p>
                </div>
                <p class="text-base font-bold text-slate-800 tabular-nums dark:text-white">{{ money(current?.amount) }}</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <span :class="LABEL">
                        Comprobante
                        <span :class="REQUIRED">*</span>
                    </span>
                    <div
                        v-if="form.receipt"
                        class="flex h-9 items-center gap-2 rounded-lg bg-slate-50 px-2.5 text-[0.75rem] ring-1 ring-inset ring-slate-200 dark:bg-white/[0.04] dark:ring-white/10"
                    >
                        <FileText class="size-3.5 shrink-0 text-slate-400 dark:text-brand-gray" />
                        <span class="min-w-0 flex-1 truncate text-slate-700 dark:text-slate-200" :title="form.receipt.name">{{ form.receipt.name }}</span>
                        <button
                            type="button"
                            class="grid size-5 shrink-0 cursor-pointer place-content-center rounded text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10 dark:hover:text-red-300"
                            :aria-label="`Quitar ${form.receipt.name}`"
                            @click="form.receipt = null"
                        >
                            <X class="size-3.5" />
                        </button>
                    </div>
                    <button
                        v-else
                        type="button"
                        class="flex h-9 w-full cursor-pointer items-center justify-center gap-2 rounded-lg border border-dashed px-3 text-[0.78rem] font-semibold transition-colors duration-150 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/15"
                        :class="
                            form.errors.receipt
                                ? 'border-red-400 text-red-600 dark:text-red-400'
                                : 'border-slate-300 bg-slate-50/70 text-slate-600 hover:border-brand/40 hover:bg-brand/[0.03] dark:border-white/15 dark:bg-white/[0.03] dark:text-slate-300 dark:hover:border-white/30'
                        "
                        @click="fileInput?.click()"
                    >
                        <Paperclip class="size-3.5" />
                        Elegir archivo · PDF o imagen
                    </button>
                    <input ref="fileInput" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="hidden" @change="pickFile" />
                    <p v-if="form.errors.receipt" :class="ERROR">{{ form.errors.receipt }}</p>
                </div>

                <div>
                    <label for="payment-paid-at" :class="LABEL">
                        Fecha de pago
                        <span :class="REQUIRED">*</span>
                    </label>
                    <div class="relative">
                        <input
                            id="payment-paid-at"
                            v-model="form.paid_at"
                            type="date"
                            required
                            :max="today()"
                            :class="FIELD"
                            :aria-invalid="Boolean(form.errors.paid_at)"
                        />
                        <CalendarDays :class="ICON" />
                    </div>
                    <p v-if="form.errors.paid_at" :class="ERROR">{{ form.errors.paid_at }}</p>
                </div>
            </div>

            <!-- Solo el segundo pago: los datos del periodo que sigue -->
            <section v-if="renews" class="rounded-xl p-3 ring-1 ring-inset ring-brand/15 dark:ring-white/10">
                <div class="mb-3 flex items-center gap-2">
                    <RefreshCw class="size-3.5 text-brand dark:text-white" />
                    <p class="text-[0.75rem] font-bold text-slate-700 dark:text-slate-200">Periodo siguiente</p>
                </div>

                <!-- Las fechas no se capturan: salen del cierre de este periodo -->
                <dl v-if="next" class="mb-3 grid gap-2 sm:grid-cols-2">
                    <div class="rounded-lg bg-slate-50/70 px-3 py-2 dark:bg-white/[0.03]">
                        <dt class="text-[0.6rem] font-bold uppercase tracking-[0.12em] text-slate-400 dark:text-brand-gray/70">Primer pago</dt>
                        <dd class="text-[0.75rem] font-semibold text-slate-700 dark:text-slate-200">{{ periodLabel(next.first) }}</dd>
                    </div>
                    <div class="rounded-lg bg-slate-50/70 px-3 py-2 dark:bg-white/[0.03]">
                        <dt class="text-[0.6rem] font-bold uppercase tracking-[0.12em] text-slate-400 dark:text-brand-gray/70">Segundo pago</dt>
                        <dd class="text-[0.75rem] font-semibold text-slate-700 dark:text-slate-200">{{ periodLabel(next.second) }}</dd>
                    </div>
                </dl>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="payment-new-policy" :class="LABEL">
                            Póliza nueva
                            <span :class="REQUIRED">*</span>
                        </label>
                        <div class="relative">
                            <input
                                id="payment-new-policy"
                                v-model="form.new_policy"
                                type="text"
                                required
                                autocomplete="off"
                                placeholder="Ej. POL-2027-0148"
                                :class="FIELD"
                                :aria-invalid="Boolean(form.errors.new_policy)"
                            />
                            <BadgeCheck :class="ICON" />
                        </div>
                        <p v-if="form.errors.new_policy" :class="ERROR">{{ form.errors.new_policy }}</p>
                    </div>

                    <div>
                        <label for="payment-new-certificate" :class="LABEL">Certificado</label>
                        <div class="relative">
                            <input
                                id="payment-new-certificate"
                                v-model="form.new_certificate"
                                type="text"
                                autocomplete="off"
                                :class="FIELD"
                                :aria-invalid="Boolean(form.errors.new_certificate)"
                            />
                            <FileText :class="ICON" />
                        </div>
                        <p v-if="form.errors.new_certificate" :class="ERROR">{{ form.errors.new_certificate }}</p>
                    </div>

                    <div>
                        <label for="payment-new-first" :class="LABEL">Importe primer pago</label>
                        <div class="relative">
                            <input
                                id="payment-new-first"
                                v-model="form.new_first_payment_amount"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="0.00"
                                :class="[FIELD, 'tabular-nums']"
                                :aria-invalid="Boolean(form.errors.new_first_payment_amount)"
                            />
                            <CircleDollarSign :class="ICON" />
                        </div>
                        <p v-if="form.errors.new_first_payment_amount" :class="ERROR">{{ form.errors.new_first_payment_amount }}</p>
                    </div>

                    <div>
                        <label for="payment-new-second" :class="LABEL">Importe segundo pago</label>
                        <div class="relative">
                            <input
                                id="payment-new-second"
                                v-model="form.new_second_payment_amount"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="0.00"
                                :class="[FIELD, 'tabular-nums']"
                                :aria-invalid="Boolean(form.errors.new_second_payment_amount)"
                            />
                            <CircleDollarSign :class="ICON" />
                        </div>
                        <p v-if="form.errors.new_second_payment_amount" :class="ERROR">{{ form.errors.new_second_payment_amount }}</p>
                    </div>
                </div>
            </section>
        </form>

        <template #footer>
            <button
                type="button"
                class="h-9 cursor-pointer rounded-xl border border-slate-200 bg-white px-4 text-[0.8rem] font-semibold text-slate-700 transition-colors duration-150 hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-500/10 dark:border-white/10 dark:bg-transparent dark:text-brand-gray dark:hover:bg-white/[0.06] dark:hover:text-white"
                @click="close"
            >
                Cancelar
            </button>
            <button
                type="submit"
                form="unit-payment-form"
                class="inline-flex h-9 cursor-pointer items-center justify-center gap-2 rounded-xl bg-brand px-4 text-[0.8rem] font-semibold text-white shadow-md shadow-brand/25 transition-all duration-150 hover:bg-brand/90 hover:shadow-lg hover:shadow-brand/30 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/25 active:translate-y-px disabled:pointer-events-none disabled:opacity-60 dark:bg-brand-light dark:shadow-black/30 dark:hover:bg-brand-light/90"
                :disabled="form.processing"
            >
                <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                <RefreshCw v-else-if="renews" class="size-4" />
                <Wallet v-else class="size-4" />
                {{ renews ? 'Registrar y renovar' : 'Registrar pago' }}
            </button>
        </template>
    </AppModal>
</template>
