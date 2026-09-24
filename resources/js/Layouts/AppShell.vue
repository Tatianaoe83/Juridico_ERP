<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import {
    CalendarCheck,
    CalendarDays,
    ChevronRight,
    FileBadge,
    FileCheck,
    KeyRound,
    Moon,
    PanelLeftClose,
    PanelLeftOpen,
    Scale,
    ShieldCheck,
    Sun,
    Truck,
    Users,
} from 'lucide-vue-next';
import { useMediaQuery } from '@vueuse/core';
import { TooltipProvider } from 'reka-ui';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import SidebarGroup from '@/components/app/SidebarGroup.vue';
import SidebarLink from '@/components/app/SidebarLink.vue';
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

/**
 * Cerrado en computadora no desaparece: se queda como riel de iconos y sigue
 * navegando. En móvil sí se esconde entero, porque ahí flota sobre el
 * contenido y un riel fijo robaría ancho a una pantalla que no lo tiene.
 */
const isDesktop = useMediaQuery('(min-width: 768px)');
const compact = computed(() => isDesktop.value && !sidebarOpen.value);

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
            // Sin título: Calendario y Compartir son temas por sí mismos, no
            // partes de uno mayor. Van fijos arriba, sin plegarse.
            group: null,
            items: [
                { href: '/calendario', label: 'Calendario', icon: CalendarDays, permission: 'calendar.view' },
                { href: '/compartido', label: 'Compartir', icon: CalendarCheck, permission: 'calendar.view' },
            ],
        },
        {
            group: 'Cumplimiento',
            items: [
                { href: '/licencias', label: 'Licencias y Permisos', icon: FileBadge, permission: 'licencias.view' },
                { href: '/flotillas', label: 'Flotillas', icon: Truck, permission: 'flotillas.view' },
                { href: '/polizas', label: 'Pólizas y Fianzas', icon: FileCheck, permission: 'polizas.view' },
            ],
        },
        {
            group: 'Administración',
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

/**
 * Solo un grupo abierto a la vez. Arranca abierto el de la página actual: el
 * layout se monta en cada visita, así que al navegar queda a la vista el
 * grupo donde uno está. Abrir otro cierra el anterior; tocar el abierto lo cierra.
 */
const currentGroup = computed(
    () => nav.value.find((section) => section.items.some((item) => page.url.split('?')[0].startsWith(item.href)))?.group,
);

// Sin página dentro de un grupo —los sueltos no cuentan— todos arrancan cerrados.
const openGroup = ref(currentGroup.value ?? null);

function toggleGroup(group) {
    openGroup.value = openGroup.value === group ? null : group;
}
</script>

<template>
    <!--
        La ventana no hace scroll: sidebar y header quedan fijos y solo <main>
        desplaza su contenido. h-dvh y no h-screen para que en móvil la barra
        del navegador no tape el final.
    -->
    <div class="flex h-dvh overflow-hidden bg-background font-corporate text-foreground">
        <!-- En móvil el sidebar flota sobre el contenido: el velo lo cierra -->
        <Transition
            enter-active-class="transition-opacity duration-300 ease-out"
            leave-active-class="transition-opacity duration-200 ease-in"
            enter-from-class="opacity-0"
            leave-to-class="opacity-0"
        >
            <div
                v-show="sidebarOpen"
                class="fixed inset-0 z-30 bg-brand-deep/50 backdrop-blur-[2px] md:hidden"
                aria-hidden="true"
                @click="sidebarOpen = false"
            />
        </Transition>

        <!--
            Hueco del sidebar. En móvil flota y entra deslizándose. En
            computadora ocupa sitio en la fila y alterna entre completo (16rem)
            y riel de iconos (4.5rem); se anima el ancho para que el contenido
            se recorra a la par en lugar de saltar.

            Todo va medido para que los iconos queden en el mismo sitio en los
            dos modos: al colapsar solo se recorta el texto, nada se mueve.

            `inert` solo en móvil cerrado: ahí sí está fuera de pantalla.
        -->
        <div
            class="fixed inset-y-0 left-0 z-40 w-64 shrink-0 overflow-hidden transition-[width,translate] duration-300 ease-[cubic-bezier(0.32,0.72,0,1)] motion-reduce:transition-none md:relative md:z-auto md:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:w-20'"
            :inert="!sidebarOpen && !isDesktop"
        >
            <TooltipProvider>
                <!--
                    Colores del login: blanco con acentos azul PROSER en claro, el azul
                    profundo del fondo del login en oscuro. Cada logo va con su fondo.
                -->
                <aside
                    class="app-sidebar @container absolute inset-y-0 left-0 flex w-full flex-col border-r border-slate-200/80 bg-white dark:border-white/[0.06] dark:bg-brand-deep"
                >
                    <!--
                        El logo lo decide el ancho real del sidebar (el <aside> es
                        @container), no el estado en JS: el completo solo aparece
                        cuando cabe (≥ 11rem) y por debajo queda el isotipo
                        centrado. Así nunca se sale del hueco, ni a media animación.
                    -->
                    <Link
                        href="/calendario"
                        class="flex h-16 shrink-0 items-center px-6 focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-inset focus-visible:ring-brand/30 @max-[11rem]:justify-center @max-[11rem]:px-0"
                        aria-label="PROSER Grupo Constructor · Inicio"
                    >
                        <span class="block animate-in fade-in-0 duration-300 @max-[11rem]:hidden">
                            <img
                                src="/images/Logo-azul.png"
                                alt=""
                                width="132"
                                height="32"
                                class="h-8 w-auto max-w-none dark:hidden"
                            />
                            <img
                                src="/images/Logo-blanco.png"
                                alt=""
                                width="132"
                                height="32"
                                class="hidden h-8 w-auto max-w-none dark:block"
                            />
                        </span>
                        <!--
                            Solo existe la versión azul del isotipo: en oscuro se
                            vuelve blanca con filtro, sobre el azul no se vería.
                        -->
                        <img
                            src="/images/LogoAzul.png"
                            alt=""
                            width="25"
                            height="32"
                            class="hidden h-8 w-auto animate-in fade-in-0 zoom-in-90 duration-300 @max-[11rem]:block dark:brightness-0 dark:invert"
                        />
                    </Link>

                    <div class="mx-6 h-px bg-gradient-to-r from-brand/15 via-brand/5 to-transparent dark:from-white/15 dark:via-white/5" />

                    <!-- Si algún día el menú no cabe, desplaza solo la lista -->
                    <nav aria-label="Principal" class="min-h-0 flex-1 space-y-3 overflow-x-hidden overflow-y-auto px-4 py-5">
                        <template v-for="(section, index) in nav" :key="section.group ?? `suelto-${index}`">
                            <!-- Sin título: enlaces fijos, siempre visibles -->
                            <div v-if="!section.group" class="space-y-1">
                                <SidebarLink
                                    v-for="item in section.items"
                                    :key="item.href"
                                    :href="item.href"
                                    :label="item.label"
                                    :icon="item.icon"
                                    :compact="compact"
                                />
                            </div>

                            <SidebarGroup
                                v-else
                                :label="section.group"
                                :items="section.items"
                                :open="openGroup === section.group"
                                :compact="compact"
                                @toggle="toggleGroup(section.group)"
                            />
                        </template>
                    </nav>

                    <!-- Tarjeta de marca: el mismo panel azul del login, en pequeño -->
                    <div class="p-4">
                        <div
                            class="brand-card flex items-center gap-3 overflow-hidden rounded-xl bg-brand p-1.5 pr-3 whitespace-nowrap text-white ring-1 ring-white/10 dark:bg-white/[0.04]"
                            :title="compact ? 'Sistema de Gestión Jurídica' : undefined"
                        >
                            <span class="grid size-9 shrink-0 place-content-center rounded-lg bg-white/10 ring-1 ring-white/15">
                                <Scale class="size-4" />
                            </span>
                            <span
                                class="min-w-0 py-1 transition-opacity duration-200"
                                :class="compact ? 'opacity-0' : 'opacity-100'"
                            >
                                <span class="block text-[0.6rem] font-semibold uppercase tracking-[0.18em] text-brand-gray">
                                    Sistema de
                                </span>
                                <span class="block truncate text-sm font-bold">Gestión Jurídica</span>
                            </span>
                        </div>
                    </div>
                </aside>
            </TooltipProvider>
        </div>

        <!-- Contenido -->
        <div class="flex min-w-0 flex-1 flex-col">
            <header
                class="relative z-20 flex h-16 shrink-0 items-center gap-3 border-b border-slate-200/80 bg-white/95 px-4 shadow-[0_1px_2px_rgb(2_29_73/0.04)] backdrop-blur md:px-6 dark:border-white/[0.06] dark:bg-brand-deep/95 dark:shadow-none"
            >
                <button
                    type="button"
                    class="grid size-9 cursor-pointer place-content-center rounded-lg text-slate-500 transition-colors duration-150 hover:bg-brand/5 hover:text-brand focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-brand/30 dark:text-brand-gray dark:hover:bg-white/10 dark:hover:text-white dark:focus-visible:ring-white/30"
                    :aria-label="sidebarOpen ? 'Contraer menú' : 'Expandir menú'"
                    :title="sidebarOpen ? 'Contraer menú' : 'Expandir menú'"
                    :aria-expanded="sidebarOpen"
                    @click="sidebarOpen = !sidebarOpen"
                >
                    <PanelLeftClose v-if="sidebarOpen" class="size-[1.1rem]" />
                    <PanelLeftOpen v-else class="size-[1.1rem]" />
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