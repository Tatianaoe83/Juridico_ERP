<script setup>
import { Store } from 'lucide-vue-next';
import { computed, watch } from 'vue';
import FormSelect from '@/components/app/FormSelect.vue';
import FormSwitch from '@/components/app/FormSwitch.vue';
import { catalogOptions } from '@/lib/catalogs';

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
    /** El useForm del padre: source, supplier_id y, en el automóvil, financing. */
    form: { type: Object, required: true },
    /** Para los id de los campos: unit, machinery… */
    prefix: { type: String, required: true },
    /** Automóvil: el beneficiario preferente sale del financiamiento. */
    vehicle: { type: Boolean, default: false },
    /** Catálogo de beneficiarios: [{ id, name }]. */
    beneficiaries: { type: Array, default: () => [] },
});

const beneficiaryOptions = computed(() => catalogOptions(props.beneficiaries));

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
            <label :for="`${prefix}-supplier`" :class="LABEL">Beneficiario <span :class="REQUIRED">*</span></label>
            <FormSelect
                :id="`${prefix}-supplier`"
                v-model="form.supplier_id"
                :options="beneficiaryOptions"
                :icon="Store"
                placeholder="Selecciona el beneficiario"
                :invalid="Boolean(form.errors.supplier_id)"
            />
            <p v-if="form.errors.supplier_id" :class="ERROR">{{ form.errors.supplier_id }}</p>
            <p v-else-if="!beneficiaries.length" :class="HINT">Todavía no hay beneficiarios en el catálogo.</p>
            <p v-else :class="HINT">Cobra primero en pérdida total.</p>
        </div>
    </div>
</template>
