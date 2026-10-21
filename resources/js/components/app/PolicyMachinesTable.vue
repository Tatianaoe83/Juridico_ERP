<script setup>
import { unitStatus } from '@/lib/units';

/**
 * Las máquinas de una póliza de maquinaria, para desplegarlas debajo de su
 * fila en las tablas de Flotillas (maquinaria y preferentes).
 */
defineProps({
    /** [{ id, machine_type, brand, model, economic_number, status }] */
    machines: { type: Array, required: true },
});

const TH = 'px-3 py-1.5 text-left text-[0.6rem] font-bold ';
</script>

<template>
    <table class="w-full overflow-hidden rounded-lg text-[0.75rem] ring-1 ring-slate-200 dark:ring-white/10">
        <thead class="bg-white text-slate-400 dark:bg-white/[0.03] dark:text-brand-gray/80">
            <tr>
                <th :class="TH">Tipo</th>
                <th :class="TH">Marca</th>
                <th :class="TH">Modelo</th>
                <th :class="TH"># Económico</th>
                <th :class="TH">Estado</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 bg-white dark:divide-white/[0.05] dark:bg-transparent">
            <tr v-for="machine in machines" :key="machine.id">
                <td class="px-3 py-1.5 font-semibold text-slate-700 dark:text-slate-200">{{ machine.machine_type ?? '—' }}</td>
                <td class="px-3 py-1.5 text-slate-700 dark:text-slate-200">{{ machine.brand }}</td>
                <td class="px-3 py-1.5 text-slate-600 dark:text-slate-300">{{ machine.model }}</td>
                <td class="px-3 py-1.5 text-slate-600 tabular-nums dark:text-slate-300">{{ machine.economic_number ?? '—' }}</td>
                <td class="px-3 py-1.5">
                    <span
                        class="inline-flex items-center rounded-md px-1.5 py-px text-[0.65rem] font-bold whitespace-nowrap ring-1 ring-inset"
                        :class="unitStatus(machine.status).tone"
                    >
                        {{ unitStatus(machine.status).label }}
                    </span>
                </td>
            </tr>
        </tbody>
    </table>
</template>
