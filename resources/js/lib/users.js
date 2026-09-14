import { Crown, ShieldCheck, UserRound, UserCog } from 'lucide-vue-next';

/** Dos iniciales en mayúscula: «María López Ruiz» → «ML». */
export function userInitials(name = '') {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase() || '?';
}

/**
 * Nombre legible, descripción e icono de cada rol. Un rol nuevo creado desde
 * /roles cae en el genérico: la interfaz no se rompe por no conocerlo.
 */
const ROLE_META = {
    superadmin: { label: 'Superadmin', description: 'Acceso total al sistema', icon: Crown },
    admin: { label: 'Administrador', description: 'Gestiona usuarios y calendario', icon: ShieldCheck },
    user: { label: 'Usuario', description: 'Consulta y agenda eventos', icon: UserRound },
};

export function roleMeta(role) {
    return ROLE_META[role] ?? { label: role, description: 'Rol personalizado', icon: UserCog };
}

/**
 * Color de la etiqueta de rol. El superadmin va en ámbar porque salta toda
 * comprobación de permisos; el admin en el azul PROSER; el resto, neutro.
 */
const TONE = {
    superadmin: 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/25',
    admin: 'bg-brand/[0.06] text-brand ring-brand/15 dark:bg-brand-light/25 dark:text-white dark:ring-brand-gray/25',
};

export function roleTone(role) {
    return TONE[role] ?? 'bg-slate-50 text-slate-600 ring-slate-500/15 dark:bg-white/[0.05] dark:text-brand-gray dark:ring-white/10';
}
