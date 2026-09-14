<script setup>
import { Link } from '@inertiajs/vue3';
import { Car, Gavel, ShieldCheck } from 'lucide-vue-next';

const year = new Date().getFullYear();
</script>

<template>
    <!--
        Desde md la página mide exactamente la ventana y no hace scroll: la
        tarjeta se ajusta al alto disponible. En móvil sí puede desplazarse.
    -->
    <div
        class="auth-backdrop relative isolate flex min-h-dvh flex-col items-center justify-center gap-4 bg-brand-deep px-3 font-corporate py-6 sm:px-6 md:h-dvh md:overflow-hidden md:py-12 lg:px-8 short:md:py-9"
    >
        <div
            class="grid min-h-0 w-full max-w-md animate-in fade-in zoom-in-[0.98] rounded-3xl bg-card p-2 text-card-foreground shadow-2xl shadow-black/50 ring-1 ring-black/5 duration-300 motion-reduce:animate-none sm:p-3 md:max-h-full md:grid-rows-[auto_minmax(0,1fr)] lg:h-full lg:max-h-[40rem] lg:max-w-4xl lg:grid-cols-[0.95fr_1fr] lg:grid-rows-1 xl:max-w-5xl 2xl:max-w-6xl dark:ring-white/10"
        >
            <!-- Panel de marca: banner compacto en pantallas chicas, columna en grandes -->
            <aside
                class="brand-panel relative isolate flex min-h-44 flex-col overflow-hidden rounded-2xl bg-brand p-5 text-white sm:min-h-48 sm:p-7 md:min-h-0 lg:p-10 short:lg:p-8"
            >
                <!-- Carretera en perspectiva: la línea central avanza hacia el horizonte -->
                <svg
                    class="pointer-events-none absolute inset-0 -z-10 size-full"
                    viewBox="0 0 400 600"
                    preserveAspectRatio="xMidYMid slice"
                    aria-hidden="true"
                >
                    <defs>
                        <radialGradient id="auth-horizon" cx="0.5" cy="0.5" r="0.5">
                            <stop offset="0" stop-color="#a7a8a9" stop-opacity="0.35" />
                            <stop offset="1" stop-color="#a7a8a9" stop-opacity="0" />
                        </radialGradient>
                        <linearGradient id="auth-asphalt" x1="0" y1="1" x2="0" y2="0">
                            <stop offset="0" stop-color="#ffffff" stop-opacity="0.07" />
                            <stop offset="1" stop-color="#ffffff" stop-opacity="0" />
                        </linearGradient>
                    </defs>

                    <circle cx="330" cy="120" r="150" fill="url(#auth-horizon)" />
                    <path d="M-20 600 L330 120 L470 600 Z" fill="url(#auth-asphalt)" />
                    <path d="M-20 600 L330 120 M470 600 L330 120" stroke="#ffffff" stroke-opacity="0.16" stroke-width="1.5" fill="none" />
                    <path class="road-dash" d="M225 600 L330 120" stroke="#a7a8a9" stroke-opacity="0.7" stroke-width="3" stroke-linecap="round" fill="none" />
                </svg>

                <!-- Oscurece la base para que el texto siempre se lea sobre la carretera -->
                <div
                    class="pointer-events-none absolute inset-x-0 bottom-0 -z-10 h-3/4 bg-gradient-to-t from-brand via-brand/85 to-transparent"
                    aria-hidden="true"
                />

                <Link
                    href="/"
                    class="w-fit rounded-md focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-white/40"
                >
                    <img
                        src="/images/Logo-blanco.png"
                        alt="PROSER Grupo Constructor"
                        width="132"
                        height="32"
                        class="h-7 w-auto sm:h-8"
                    />
                </Link>

                <!-- Casos de ejemplo flotando: decorativos, solo con espacio de sobra -->
                <div class="relative hidden min-h-0 flex-1 lg:block" aria-hidden="true">
                    <div class="float-card top-[10%] left-0" style="--delay: 0s">
                        <span class="float-icon bg-white/10 text-white"><Car class="size-4" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-xs font-bold text-white">Unidad ECO-214</span>
                            <span class="block truncate text-[0.7rem] text-brand-gray">Siniestro de tránsito</span>
                        </span>
                        <span class="rounded-full bg-brand-gray/20 px-2 py-0.5 text-[0.65rem] text-brand-gray">En curso</span>
                    </div>

                    <div class="float-card top-[42%] right-0" style="--delay: -2.5s">
                        <span class="float-icon bg-white/10 text-white"><Gavel class="size-4" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-xs font-bold text-white">Audiencia de conciliación</span>
                            <span class="block truncate text-[0.7rem] text-brand-gray">Mañana · 10:00 h</span>
                        </span>
                    </div>

                    <div class="float-card float-card-extra bottom-[6%] left-[8%]" style="--delay: -5s">
                        <span class="float-icon bg-white/10 text-white"><ShieldCheck class="size-4" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-xs font-bold text-white">Póliza renovada</span>
                            <span class="block truncate text-[0.7rem] text-brand-gray">Flotilla norte · 38 unidades</span>
                        </span>
                    </div>
                </div>

                <!-- En md bajo el banner solo lleva el logo: el formulario tiene prioridad -->
                <div class="mt-auto pt-8 short:md:max-lg:hidden lg:max-w-md lg:pt-6">
                    <p class="flex items-center gap-2 text-[0.7rem] font-semibold uppercase tracking-[0.2em] text-brand-gray">
                        <span class="size-1.5 rounded-full bg-brand-gray" aria-hidden="true" />
                        Sistema de Gestión Jurídica
                    </p>
                    <h2
                        class="mt-3 text-xl font-bold leading-tight text-balance sm:text-2xl lg:text-4xl lg:leading-[1.1] short:lg:text-3xl"
                    >
                        Cada unidad de tu flotilla, <span class="text-brand-gray">en regla</span>.
                    </h2>
                    <p class="mt-3 hidden text-sm leading-relaxed text-brand-gray sm:max-md:block lg:block">
                        Siniestros, multas, trámites y audiencias de todos tus vehículos en un solo lugar.
                    </p>
                </div>
            </aside>

            <!--
                my-auto y no items-center: si la ventana es tan baja que el
                formulario no cabe, centrar recortaría la parte de arriba sin
                forma de alcanzarla. Así solo se desplaza esta columna.
            -->
            <main class="flex min-h-0 flex-col overflow-y-auto px-3 pb-6 pt-8 sm:px-8 sm:pb-10 sm:pt-10 md:py-8 lg:px-10 xl:px-12 short:md:py-6">
                <div class="mx-auto my-auto w-full max-w-sm">
                    <slot />
                </div>
            </main>
        </div>

        <p class="text-xs text-brand-gray/70 md:absolute md:inset-x-0 md:bottom-4 md:text-center short:md:bottom-2.5">
            © {{ year }} PROSER · Sistema de Gestión Jurídica
        </p>
    </div>
</template>

<style scoped>
/*
 * Sin fotografía: la textura sale de gradientes. Resplandor ámbar de la marca
 * arriba a la izquierda y una retícula tenue que se desvanece hacia el centro.
 */
.auth-backdrop::before {
    content: '';
    position: absolute;
    inset: 0;
    z-index: -1;
    background:
        radial-gradient(55% 45% at 12% 8%, rgb(43 77 134 / 0.55), transparent 70%),
        radial-gradient(45% 50% at 92% 95%, rgb(167 168 169 / 0.1), transparent 70%),
        linear-gradient(to right, rgb(255 255 255 / 0.035) 1px, transparent 1px) 0 0 / 40px 40px,
        linear-gradient(to bottom, rgb(255 255 255 / 0.035) 1px, transparent 1px) 0 0 / 40px 40px;
    mask-image: radial-gradient(120% 100% at 50% 50%, #000 40%, transparent 100%);
}

.brand-panel {
    background-image: radial-gradient(90% 60% at 0% 100%, rgb(43 77 134 / 0.6), transparent 60%), radial-gradient(70% 50% at 100% 0%, rgb(1 15 40 / 0.7), transparent 70%);
}

/* La línea central avanza: da sensación de movimiento sin distraer. */
.road-dash {
    stroke-dasharray: 16 22;
    animation: road 1.4s linear infinite;
}

@keyframes road {
    to {
        stroke-dashoffset: -38;
    }
}

.float-card {
    position: absolute;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    width: 15rem;
    max-width: 90%;
    padding: 0.7rem 0.8rem;
    border-radius: 0.9rem;
    background: rgb(255 255 255 / 0.06);
    box-shadow:
        inset 0 0 0 1px rgb(255 255 255 / 0.1),
        0 12px 30px -10px rgb(0 0 0 / 0.6);
    backdrop-filter: blur(12px);
    animation: float 7s ease-in-out infinite;
    animation-delay: var(--delay);
}

.float-icon {
    display: grid;
    place-content: center;
    flex-shrink: 0;
    width: 2.1rem;
    height: 2.1rem;
    border-radius: 0.6rem;
}

@keyframes float {
    0%,
    100% {
        transform: translate3d(0, 0, 0);
    }
    50% {
        transform: translate3d(0, -8px, 0);
    }
}

/* En ventanas bajas la tercera tarjeta chocaría con el titular. */
@media (max-height: 760px) {
    .float-card-extra {
        display: none;
    }
}

@media (prefers-reduced-motion: reduce) {
    .road-dash,
    .float-card {
        animation: none;
    }
}
</style>
