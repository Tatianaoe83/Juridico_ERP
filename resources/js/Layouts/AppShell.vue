<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import {
    Bell,
    CalendarCheck,
    CalendarDays,
    ChevronRight,
    KeyRound,
    Moon,
    PanelLeft,
    ShieldCheck,
    Sun,
    Users,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import SidebarGroup from '@/components/app/SidebarGroup.vue';
import UserMenu from '@/components/app/UserMenu.vue';
import { Toaster } from '@/components/ui/sonner';
import { usePermissions } from '@/composables/usePermissions';
import { useTheme } from '@/composables/useTheme';

defineProps({
    /** [{ label, href? }] — la última entrada es la página actual. */
    breadcrumbs: { type: Array, default: () => [] },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? {});
const { isDark, toggle: toggleTheme } = useTheme();
const { can } = usePermissions();

const sidebarOpen = ref(localStorage.getItem('sidebar') !== 'closed');

watch(sidebarOpen, (open) => localStorage.setItem('sidebar', open ? 'open' : 'closed'));

// Mensajes flash del backend
watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    },
    { deep: true, immediate: true },
);

/**
 * El menú se filtra por permiso, no por rol: así un rol nuevo hereda las
 * entradas correctas sin tocar este archivo.
 *
 * Ocultar solo ordena la vista; quien autoriza es el middleware `can:` de cada
 * ruta. Escribir la URL a mano sigue devolviendo 403.
 */
const nav = computed(() =>
    [
        {
            group: 'General',
            items: [
                { href: '/calendario', label: 'Calendario', icon: CalendarDays, permission: 'calendar.view' },
                { href: '/compartido', label: 'Compartir', icon: CalendarCheck, permission: 'calendar.view' },
            ],
        },
        {
            group: 'Administración',
            collapsible: true,
            items: [
                { href: '/usuarios', label: 'Usuarios', icon: Users, permission: 'users.view' },
                { href: '/roles', label: 'Roles', icon: ShieldCheck, permission: 'roles.manage' },
                { href: '/permisos', label: 'Permisos', icon: KeyRound, permission: 'roles.manage' },
            ],
        },
    ]
        .map((section) => ({ ...section, items: section.items.filter((item) => can(item.permission)) }))
        .filter((section) => section.items.length),
);
</script>

<template>
    <div class="flex min-h-screen bg-background text-foreground">
        <!-- Sidebar -->
        <aside
            v-show="sidebarOpen"
            class="fixed inset-y-0 left-0 z-30 flex w-64 flex-col border-r bg-card md:static"
        >
            <Link href="/calendario" class="flex items-center gap-2.5 border-b px-4 py-4">
                <svg class="h-7 w-8 shrink-0 fill-current" viewBox="0 0 34 28" aria-hidden="true">
                    <rect x="0" y="16" width="6" height="12" />
                    <rect x="9" y="8" width="6" height="20" />
                    <rect x="18" y="0" width="6" height="28" />
                    <rect x="27" y="12" width="6" height="16" opacity="0.45" />
                </svg>
                <span class="min-w-0">
                    <span class="block truncate text-sm font-bold tracking-[0.18em]">PROSER</span>
                    <span class="block truncate text-[0.6rem] uppercase tracking-[0.18em] text-muted-foreground">
                        Gestión de TI
                    </span>
                </span>
            </Link>

            <nav class="flex-1 space-y-4 overflow-y-auto p-3">
                <SidebarGroup
                    v-for="section in nav"
                    :key="section.group"
                    :label="section.group"
                    :items="section.items"
                    :collapsible="Boolean(section.collapsible)"
                />
            </nav>

            <div class="border-t p-2">
                <UserMenu :user="user" />
            </div>
        </aside>

        <!-- Contenido -->
        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex h-14 items-center gap-3 border-b bg-card px-4">
                <button
                    type="button"
                    class="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                    :aria-label="sidebarOpen ? 'Ocultar menú' : 'Mostrar menú'"
                    @click="sidebarOpen = !sidebarOpen"
                >
                    <PanelLeft class="size-4" />
                </button>

                <nav aria-label="Ruta" class="flex min-w-0 items-center gap-1.5 text-sm">
                    <template v-for="(crumb, i) in breadcrumbs" :key="crumb.label">
                        <ChevronRight v-if="i > 0" class="size-3.5 shrink-0 text-muted-foreground" />
                        <Link
                            v-if="crumb.href && i < breadcrumbs.length - 1"
                            :href="crumb.href"
                            class="truncate text-muted-foreground hover:text-foreground"
                        >
                            {{ crumb.label }}
                        </Link>
                        <span
                            v-else
                            class="truncate"
                            :class="i === breadcrumbs.length - 1 ? 'font-medium' : 'text-muted-foreground'"
                        >
                            {{ crumb.label }}
                        </span>
                    </template>
                </nav>

                <div class="ml-auto flex items-center gap-1">
                    <button
                        type="button"
                        class="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                        :aria-label="isDark ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro'"
                        @click="toggleTheme"
                    >
                        <Sun v-if="isDark" class="size-6" />
                        <Moon v-else class="size-6" />
                    </button>
                </div>
            </header>

            <main class="flex-1 bg-muted/30 p-4 md:p-6">
                <slot />
            </main>
        </div>

        <Toaster position="bottom-right" rich-colors />
    </div>
</template>