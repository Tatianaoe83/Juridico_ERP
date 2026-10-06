import { Ban, CircleHelp, Clock, ShieldAlert, ShieldCheck, ShieldX } from 'lucide-vue-next';

/** Pólizas y fianzas comparten tabla: cada renglón dice cuál es. */
export const COVERAGE_TYPES = {
    policy: { label: 'Póliza', plural: 'Pólizas', tone: 'bg-brand/[0.07] text-brand dark:bg-white/10 dark:text-white' },
    bond: { label: 'Fianza', plural: 'Fianzas', tone: 'bg-violet-50 text-violet-700 dark:bg-violet-400/10 dark:text-violet-300' },
};

/** Qué cubre la póliza. Igual que UnitPolicy::COVERAGES; las mismas para vehículos y maquinaria. */
export const POLICY_COVERAGES = {
    civil_liability: 'Responsabilidad civil',
    limited: 'Limitada',
    broad: 'Amplia',
    broad_plus: 'Amplia plus',
};

/** Cómo va su vigencia. Igual que App\Support\CoverageStatus. */
export const COVERAGE_STATUS = {
    active: {
        label: 'Vigente',
        icon: ShieldCheck,
        tone: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-400/10 dark:text-emerald-300 dark:ring-emerald-400/25',
    },
    expiring: {
        label: 'Por vencer',
        icon: Clock,
        tone: 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/25',
    },
    expired: {
        label: 'Vencida',
        icon: ShieldAlert,
        tone: 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-400/10 dark:text-red-300 dark:ring-red-400/25',
    },
    cancelling: {
        label: 'En cancelación',
        icon: ShieldX,
        tone: 'bg-orange-50 text-orange-700 ring-orange-600/20 dark:bg-orange-400/10 dark:text-orange-300 dark:ring-orange-400/25',
    },
    cancelled: {
        label: 'Cancelada',
        icon: Ban,
        tone: 'bg-slate-50 text-slate-600 ring-slate-500/15 dark:bg-white/[0.05] dark:text-brand-gray dark:ring-white/10',
    },
    unknown: {
        label: 'Sin vigencia',
        icon: CircleHelp,
        tone: 'bg-slate-50 text-slate-600 ring-slate-500/15 dark:bg-white/[0.05] dark:text-brand-gray dark:ring-white/10',
    },
};

export function coverageStatus(value) {
    return COVERAGE_STATUS[value] ?? COVERAGE_STATUS.unknown;
}
