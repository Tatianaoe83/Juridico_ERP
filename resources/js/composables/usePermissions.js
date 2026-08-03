import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Lee los roles y permisos que HandleInertiaRequests comparte en cada visita.
 *
 * Esto solo ordena la interfaz: esconder un botón no impide llamar a la ruta.
 * Quien autoriza de verdad es el middleware `can:` y las Policies del servidor;
 * si algo se muestra de más aquí, el backend responde 403 igual.
 */
export function usePermissions() {
    const page = usePage();

    const user = computed(() => page.props.auth?.user ?? null);
    const roles = computed(() => user.value?.roles ?? []);
    const permissions = computed(() => user.value?.permissions ?? []);

    /** El superadmin no tiene permisos marcados: los salta con Gate::before. */
    const isSuperadmin = computed(() => roles.value.includes('superadmin'));

    function can(permission) {
        return isSuperadmin.value || permissions.value.includes(permission);
    }

    function canAny(...wanted) {
        return isSuperadmin.value || wanted.some((permission) => permissions.value.includes(permission));
    }

    function hasRole(role) {
        return roles.value.includes(role);
    }

    return { user, roles, permissions, isSuperadmin, can, canAny, hasRole };
}
