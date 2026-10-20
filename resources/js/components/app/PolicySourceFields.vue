<script setup>
import { Store } from 'lucide-vue-next';
import { computed, nextTick, watch } from 'vue';
import FormSwitch from '@/components/app/FormSwitch.vue';

/**
 * El beneficiario preferente, igual en automóviles y en maquinaria. Es lo que
 * decide el origen de la póliza: con beneficiario preferente es la del
 * proveedor (la aseguradora la elige quien vende, financia o arrenda la
 * unidad); sin él, la empresarial. Prendido pide quién es el proveedor. En el
 * automóvil solo se puede prender si la unidad está financiada; el interruptor
 * del financiamiento entra por el slot, antes que todo. Escribe directo en el
 * formulario del padre (useForm).
 */
const props = defineProps({
    /** El useForm del padre: source, supplier y, en el automóvil, financing. */
    form: { type: Object, required: true },
    /** Para los id de los campos: unit, machinery… */
    prefix: { type: String, required: true },
    /** Automóvil: el beneficiario preferente sale del financiamiento. */
    vehicle: { type: Boolean, default: false },
});

/** En el automóvil, sin financiamiento no hay beneficiario preferente. */
const available = computed(() => !props.vehicle || props.form.financing);

const preferred = computed({
    get: () => props.form.source === 'supplier',
    set: (value) => (props.form.source = value ? 'supplier' : 'corporate'),
});

/** Si se quita el financiamiento, el beneficiario se va con él. */
watch(
    () => props.form.financing,
    (financing) => {
        if (props.vehicle && !financing) props.form.source = 'corporate';
    },
);

/** Igual que en el resto del formulario: mayúsculas mientras escribe, sin mover el cursor. */
function toUpper(field, event) {
    const input = event.target;
    const { selectionStart, selectionEnd } = input;

    props.form[field] = input.value.toUpperCase();
    nextTick(() => input.setSelectionRange(selectionStart, selectionEnd));
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

const HINT = 'mt-1 text-[0.7rem] text-slate-400 dark:text-brand-gray/70';
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <!-- Lo que va antes del beneficiario: en el automóvil, el financiamiento -->
        <slot />

        <FormSwitch
            :id="`${prefix}-preferred`"
            v-model="preferred"
            label="Beneficiario preferente"
            on="Con beneficiario preferente"
            off="Sin beneficiario preferente"
            :disabled="!available"
            :error="form.errors.source"
            :hint="available ? 'La aseguradora la elige el proveedor.' : 'Solo con financiamiento.'"
        />

        <!-- Quién es: la agencia, financiera o arrendadora -->
        <div v-if="preferred">
            <label :for="`${prefix}-supplier`" :class="LABEL">Proveedor <span :class="REQUIRED">*</span></label>
            <div class="relative">
                <input
                    :id="`${prefix}-supplier`"
                    :value="form.supplier"
                    @input="toUpper('supplier', $event)"
                    type="text"
                    autocomplete="off"
                    placeholder="Agencia, financiera o arrendadora"
                    :class="FIELD"
                    :aria-invalid="Boolean(form.errors.supplier)"
                />
                <Store :class="ICON" />
            </div>
            <p v-if="form.errors.supplier" :class="ERROR">{{ form.errors.supplier }}</p>
            <p v-else :class="HINT">Cobra primero en pérdida total.</p>
        </div>
    </div>
</template>
