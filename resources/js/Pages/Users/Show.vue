<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarCheck,
    CalendarPlus,
    CalendarX,
    CircleAlert,
    CircleCheck,
    Clock,
    KeyRound,
    Mail,
    Pencil,
    Share2,
    ShieldCheck,
    Trash2,
    UserRound,
} from 'lucide-vue-next';
import AppShell from '@/Layouts/AppShell.vue';
import { Button } from '@/components/ui/button';
import { useSwal } from '@/composables/useSwal';

const props = defineProps({
    person: { type: Object, required: true },
});

const breadcrumbs = [
    { label: 'Inicio', href: '/calendario' },
    { label: 'Usuarios', href: '/usuarios' },
    { label: props.person.name },
];

const { confirmDelete, blocks } = useSwal();

/** Mismos tonos que el listado, para que un rol se reconozca de una pantalla a otra. */
const TONE = {
    superadmin: 'border-amber-500/40 bg-amber-500/10 text-amber-600 dark:text-amber-400',
    admin: 'border-[#459AF7]/40 bg-[#459AF7]/10 text-[#2B7FD6] dark:text-[#7CBAFA]',
};

const toneOf = (role) => TONE[role] ?? 'border-input text-muted-foreground';

function initials(name) {
    return name
        .split(' ')
        .slice(0, 2)
        .map((part) => part[0] ?? '')
        .join('')
        .toUpperCase();
}

function datetime(iso) {
    return iso
        ? new Date(iso).toLocaleDateString('es-MX', {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        })
        : '—';
}

/** Fecha corta, para cuando la hora exacta no aporta. */
function day(iso) {
    return iso
        ? new Date(iso).toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric' })
        : '—';
}

/**
 * «hace 3 días» en lugar de una fecha: en «último cambio» lo que importa es si
 * fue hace un rato o hace meses, no el día exacto —ese va en el `title`.
 */
function ago(iso) {
    if (!iso) return '—';

    const seconds = Math.round((Date.now() - new Date(iso)) / 1000);

    const UNITS = [
        ['year', 31536000],
        ['month', 2592000],
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60],
    ];

    const format = new Intl.RelativeTimeFormat('es-MX', { numeric: 'auto' });

    for (const [unit, size] of UNITS) {
        if (seconds >= size) {
            return format.format(-Math.floor(seconds / size), unit);
        }
    }

    return 'hace un momento';
}

/** Los permisos se agrupan por su prefijo, igual que en la matriz de /permisos. */
function areaOf(permission) {
    return permission.split('.')[0];
}

async function remove() {
    const { lead, chip, panel, note, stack } = blocks;

    const ok = await confirmDelete({
        title: '¿Eliminar esta cuenta?',
        html: stack(
            lead(
                `<span class="font-medium">${props.person.name}</span><br>` +
                    `<span class="mt-1.5 inline-block">${chip(props.person.email)}</span>`,
            ),
            panel({
                label: 'Qué se pierde',
                tone: 'danger',
                items: [
                    'La cuenta y su acceso al sistema',
                    'Sus tokens de API y la vinculación con Microsoft',
                    'No se puede deshacer',
                ],
            }),
            note('Sus eventos siguen en Outlook: esto solo borra la cuenta de esta app.'),
        ),
        confirmText: 'Eliminar cuenta',
    });

    if (!ok) return;

    router.delete(`/usuarios/${props.person.id}`);
}
</script>

<template>
    <Head :title="person.name" />

    <AppShell :breadcrumbs="breadcrumbs">
        <!-- Columna centrada: a lo ancho de la pantalla las dos tarjetas quedaban
             estiradas, con el dato pegado al borde y metros de vacío en medio -->
        <div class="mx-auto w-full max-w-4xl">
        <div class="mb-5 flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="grid size-12 shrink-0 place-content-center rounded-full bg-muted text-sm font-medium">
                    {{ initials(person.name) }}
                </span>
                <div class="min-w-0">
                    <h1 class="truncate text-2xl font-semibold tracking-tight">
                        {{ person.name }}
                        <span v-if="person.is_self" class="text-base font-normal text-muted-foreground">· tú</span>
                    </h1>
                    <p class="truncate text-sm text-muted-foreground">{{ person.email }}</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <Button variant="outline" class="gap-1.5" as-child>
                    <Link href="/usuarios">
                        <ArrowLeft class="size-4" />
                        Volver
                    </Link>
                </Button>
                <Button v-if="person.can.update" class="gap-1.5" as-child>
                    <Link :href="`/usuarios/${person.id}/editar`">
                        <Pencil class="size-4" />
                        Editar
                    </Link>
                </Button>
                <Button v-if="person.can.delete" variant="outline" class="gap-1.5 text-destructive" @click="remove">
                    <Trash2 class="size-4" />
                    Eliminar
                </Button>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="rounded-xl border bg-card">
                <h2 class="flex items-center gap-2 border-b px-4 py-3 text-sm font-medium">
                    <ShieldCheck class="size-4 text-muted-foreground" />
                    Acceso
                </h2>

                <!-- Icono a la izquierda, etiqueta encima del valor. El dato queda
                     alineado en una sola columna en vez de irse al borde derecho -->
                <dl class="grid gap-4 p-4 text-sm">
                    <div class="flex items-start gap-3">
                        <UserRound class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                        <div class="min-w-0">
                            <dt class="text-xs font-bold uppercase tracking-wide text-foreground">
                                Rol
                            </dt>
                            <dd class="mt-1 flex flex-wrap items-center gap-1.5">
                                <span
                                    v-for="role in person.roles"
                                    :key="role"
                                    class="rounded-full border px-2.5 py-0.5 font-mono text-[0.7rem] uppercase tracking-wide"
                                    :class="toneOf(role)"
                                >
                                    {{ role }}
                                </span>

                                <span
                                    v-if="!person.roles.length"
                                    class="inline-flex items-center gap-1.5 rounded-full border border-destructive/30 bg-destructive/5 px-2.5 py-0.5 text-xs text-destructive"
                                >
                                    <CircleAlert class="size-3.5" />
                                    Sin rol · no puede abrir ninguna pantalla
                                </span>
                            </dd>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <Mail class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                        <div class="min-w-0">
                            <dt class="text-xs font-bold uppercase tracking-wide text-foreground">
                                Correo
                            </dt>
                            <dd class="mt-0.5 truncate">{{ person.email }}</dd>
                            <dd
                                v-if="person.email_verified_at"
                                class="mt-0.5 inline-flex items-center gap-1 text-xs text-emerald-600 dark:text-emerald-400"
                                :title="`Verificado el ${datetime(person.email_verified_at)}`"
                            >
                                <CircleCheck class="size-3" />
                                Verificado
                            </dd>
                            <dd v-else class="mt-0.5 inline-flex items-center gap-1 text-xs text-muted-foreground">
                                <CircleAlert class="size-3" />
                                Sin verificar
                            </dd>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <CalendarPlus class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                        <div class="min-w-0">
                            <dt class="text-xs font-bold uppercase tracking-wide text-foreground">
                                Alta
                            </dt>
                            <!-- Fecha corta a la vista, exacta en el title -->
                            <dd class="mt-0.5" :title="datetime(person.created_at)">
                                {{ day(person.created_at) }}
                            </dd>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <Clock class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                        <div class="min-w-0">
                            <dt class="text-xs font-bold uppercase tracking-wide text-foreground">
                                Último cambio
                            </dt>
                            <dd class="mt-0.5" :title="datetime(person.updated_at)">
                                {{ day(person.updated_at) }}
                                <span class="text-xs text-muted-foreground">· {{ ago(person.updated_at) }}</span>
                            </dd>
                        </div>
                    </div>
                </dl>
            </section>

            <section class="rounded-xl border bg-card p-4">
                <h2 class="mb-3 flex items-center gap-2 text-sm font-medium">
                    <CalendarCheck class="size-4 text-muted-foreground" />
                    Microsoft 365
                </h2>

                <div v-if="person.microsoft" class="grid gap-2.5 text-sm">
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-muted-foreground">Cuenta</dt>
                        <dd class="truncate text-right">{{ person.microsoft.email }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-muted-foreground">Vinculada</dt>
                        <dd class="text-right">{{ datetime(person.microsoft.linked_at) }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-muted-foreground">Última sincronía</dt>
                        <dd class="text-right">{{ datetime(person.microsoft.synced_at) }}</dd>
                    </div>

                    <p
                        v-if="!person.microsoft.can_read_calendar"
                        class="mt-1 flex items-start gap-2 rounded-lg border bg-muted/30 px-3 py-2 text-xs text-muted-foreground"
                    >
                        <CalendarX class="mt-0.5 size-3.5 shrink-0" />
                        Entró con Microsoft, pero no concedió lectura del calendario. Falta el
                        consentimiento de <code class="rounded bg-muted px-1 font-mono">Calendars.Read</code>.
                    </p>

                    <!-- Con quién comparte su calendario -->
                    <div v-if="person.microsoft.shared_with.length" class="mt-1 border-t pt-2.5">
                        <dt class="mb-1.5 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-foreground">
                            <Share2 class="size-3.5 text-muted-foreground" />
                            Comparte su calendario con
                        </dt>
                        <dd class="flex flex-wrap gap-1.5">
                            <span
                                v-for="share in person.microsoft.shared_with"
                                :key="share.email"
                                class="inline-flex items-center gap-1 rounded-full border bg-muted/30 px-2.5 py-0.5 text-xs"
                                :title="`Rol: ${share.role}`"
                            >
                                {{ share.email }}
                            </span>
                        </dd>
                    </div>
                </div>

                <div v-else-if="person.guest_on.length" class="grid gap-2.5 text-sm">
                    <p class="text-xs text-muted-foreground">Sin cuenta propia. Entra a estos calendarios:</p>

                    <div
                        v-for="cal in person.guest_on"
                        :key="cal.owner_email"
                        class="flex items-start justify-between gap-4 rounded-lg border bg-muted/30 px-3 py-2"
                    >
                        <div class="min-w-0">
                            <p class="truncate font-medium">{{ cal.calendar_name }}</p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ cal.owner_name }} · {{ cal.owner_email }}
                            </p>
                        </div>
                        <span class="shrink-0 self-center rounded-full border px-2 py-0.5 font-mono text-[0.7rem] uppercase text-muted-foreground">
                            {{ cal.role }}
                        </span>
                    </div>
                </div>

                <p v-else class="text-sm text-muted-foreground">
                    Sin cuenta de Microsoft vinculada.
                </p>
            </section>

            <section class="rounded-xl border bg-card p-4 lg:col-span-2">
                <h2 class="mb-3 flex items-center gap-2 text-sm font-medium">
                    <KeyRound class="size-4 text-muted-foreground" />
                    Permisos efectivos
                    <span class="text-xs font-normal text-muted-foreground">
                        ({{ person.permissions.length }})
                    </span>
                </h2>

                <p v-if="person.roles.includes('superadmin')" class="text-sm text-muted-foreground">
                    El superadmin no lleva permisos marcados: pasa por el Gate y los tiene todos,
                    incluidos los que se agreguen después.
                </p>

                <div v-else-if="person.permissions.length" class="flex flex-wrap gap-1.5">
                    <code
                        v-for="permission in person.permissions"
                        :key="permission"
                        class="rounded border bg-muted/40 px-1.5 py-0.5 font-mono text-[0.7rem]"
                        :title="`Área: ${areaOf(permission)}`"
                    >
                        {{ permission }}
                    </code>
                </div>

                <p v-else class="text-sm text-muted-foreground">
                    Sin permisos. Esta cuenta entra pero choca con un 403 en cualquier pantalla.
                </p>
            </section>
        </div>
        </div>
    </AppShell>
</template>
