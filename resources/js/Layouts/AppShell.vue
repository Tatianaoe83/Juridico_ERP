<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import {
    CalendarCheck,
    CalendarDays,
    ChevronRight,
    KeyRound,
    Moon,
    PanelLeft,
    Scale,
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
    <!--
        La ventana no hace scroll: sidebar y header quedan fijos y solo <main>
        desplaza su contenido. h-dvh y no h-screen para que en móvil la barra
        del navegador no tape el final.
    -->
    <div class="flex h-dvh overflow-hidden bg-background font-corporate text-foreground">
        <!-- En móvil el sidebar flota sobre el contenido: el velo lo cierra -->
        <div
            v-show="sidebarOpen"
            class="fixed inset-0 z-30 bg-brand-deep/50 backdrop-blur-[2px] md:hidden"
            aria-hidden="true"
            @click="sidebarOpen = false"
        />

        <!--
            Colores del login: blanco con acentos azul PROSER en claro, el azul
            profundo del fondo del login en oscuro. Cada logo va con su fondo.
        -->
        <aside
            v-show="sidebarOpen"
            class="app-sidebar fixed inset-y-0 left-0 z-40 flex w-64 shrink-0 flex-col border-r border-slate-200/80 bg-white md:static dark:border-white/[0.06] dark:bg-brand-deep"
        >
            <Link
                href="/calendario"
                class="flex h-16 shrink-0 items-center px-6 focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-inset focus-visible:ring-brand/30"
            >
                <img
                    src="/images/Logo-azul.png"
                    alt="PROSER Grupo Constructor"
                    width="132"
                    height="32"
                    class="h-8 w-auto dark:hidden"
                />
                <img
                    src="/images/Logo-blanco.png"
                    alt="PROSER Grupo Constructor"
                    width="132"
                    height="32"
                    class="hidden h-8 w-auto dark:block"
                />
            </Link>

            <div class="mx-6 h-px bg-gradient-to-r from-brand/15 via-brand/5 to-transparent dark:from-white/15 dark:via-white/5" />

            <!-- Si algún día el menú no cabe, desplaza solo la lista -->
            <nav aria-label="Principal" class="min-h-0 flex-1 space-y-6 overflow-y-auto px-4 py-5">
                <SidebarGroup
                    v-for="section in nav"
                    :key="section.group"
                    :label="section.group"
                    :items="section.items"
                    :collapsible="Boolean(section.collapsible)"
                />
            </nav>

            <!-- Tarjeta de marca: el mismo panel azul del login, en pequeño -->
            <div class="p-4">
                <div
                    class="brand-card relative overflow-hidden rounded-xl bg-brand px-4 py-3.5 text-white ring-1 ring-white/10 dark:bg-white/[0.04]"
                >
                    <div class="flex items-center gap-3">
                        <span class="grid size-9 shrink-0 place-content-center rounded-lg bg-white/10 ring-1 ring-white/15">
                            <Scale class="size-4" />
                        </span>
                        <span class="min-w-0">
                            <span class="block text-[0.6rem] font-semibold uppercase tracking-[0.18em] text-brand-gray">
                                Sistema de
                            </span>
                            <span class="block truncate text-sm font-bold">Gestión Jurídica</span>
                        </span>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Contenido -->
        <div class="flex min-w-0 flex-1 flex-col">
            <header
                class="relative z-20 flex h-16 shrink-0 items-center gap-3 border-b border-slate-200/80 bg-white/95 px-4 shadow-[0_1px_2px_rgb(2_29_73/0.04)] backdrop-blur md:px-6 dark:border-white/[0.06] dark:bg-brand-deep/95 dark:shadow-none"
            >
                <button
                    type="button"
                    class="grid size-9 cursor-pointer place-content-center rounded-lg text-slate-500 transition-colors duration-150 hover:bg-brand/5 hover:text-brand focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-brand/30 dark:text-brand-gray dark:hover:bg-white/10 dark:hover:text-white dark:focus-visible:ring-white/30"
                    :aria-label="sidebarOpen ? 'Ocultar menú' : 'Mostrar menú'"
                    :aria-expanded="sidebarOpen"
                    @click="sidebarOpen = !sidebarOpen"
                >
                    <PanelLeft class="size-[1.1rem]" />
                </button>

                <span class="hidden h-6 w-px bg-slate-200 sm:block dark:bg-white/10" aria-hidden="true" />

                <nav aria-label="Ruta" class="flex min-w-0 items-center gap-1.5 text-sm">
                    <template v-for="(crumb, i) in breadcrumbs" :key="crumb.label">
                        <ChevronRight v-if="i > 0" class="size-3.5 shrink-0 text-slate-300 dark:text-white/25" />
                        <Link
                            v-if="crumb.href && i < breadcrumbs.length - 1"
                            :href="crumb.href"
                            class="truncate text-muted-foreground hover:text-brand dark:text-brand-gray dark:hover:text-white"
                        >
                            {{ crumb.label }}
                        </Link>
                        <span
                            v-else
                            class="truncate"
                            :class="
                                i === breadcrumbs.length - 1
                                    ? 'font-semibold text-brand dark:text-white'
                                    : 'text-muted-foreground dark:text-brand-gray'
                            "
                        >
                            {{ crumb.label }}
                        </span>
                    </template>
                </nav>

                <div class="ml-auto flex items-center gap-2 sm:gap-3">
                    <button
                        type="button"
                        class="grid size-9 shrink-0 cursor-pointer place-content-center rounded-full text-slate-500 ring-1 ring-slate-200 transition-colors duration-150 hover:bg-slate-100 hover:text-brand focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-brand/30 dark:text-brand-gray dark:ring-white/10 dark:hover:bg-white/[0.06] dark:hover:text-white dark:focus-visible:ring-white/30"
                        :aria-label="isDark ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro'"
                        :title="isDark ? 'Tema claro' : 'Tema oscuro'"
                        @click="toggleTheme"
                    >
                        <Sun v-if="isDark" class="size-[1.1rem]" />
                        <Moon v-else class="size-[1.1rem]" />
                    </button>

                    <span class="h-6 w-px bg-slate-200 dark:bg-white/10" aria-hidden="true" />

                    <UserMenu :user="user" />
                </div>
            </header>

            <!-- Único contenedor con scroll de la app -->
            <main class="min-h-0 flex-1 overflow-y-auto overscroll-contain bg-slate-50 p-4 md:p-6 dark:bg-background">
                <slot />
            </main>
        </div>

        <Toaster position="bottom-right" rich-colors />
    </div>
</template>

<style scoped>
/* Mismos resplandores que el panel de marca del login. */
.brand-card {
    background-image:
        radial-gradient(120% 90% at 0% 100%, rgb(43 77 134 / 0.7), transparent 65%),
        radial-gradient(80% 80% at 100% 0%, rgb(1 15 40 / 0.6), transparent 70%);
}

/* En oscuro el sidebar lleva el brillo azul de la esquina del fondo del login. */
:global(.dark) .app-sidebar {
    background-image: radial-gradient(90% 40% at 0% 0%, rgb(43 77 134 / 0.28), transparent 70%);
}
</style>