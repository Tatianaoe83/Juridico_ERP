<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    /** Texto del bloque de rótulo (esquina inferior de la hoja). */
    sheet: { type: String, default: 'SGTI-01' },
});

const revision = new Date().toISOString().slice(0, 7).replace('-', '.');
</script>

<template>
    <div class="auth-shell">
        <!-- Hoja técnica: retícula, marcas de registro y bloque de rótulo -->
        <section class="sheet">
            <div class="sheet-grid" aria-hidden="true" />
            <div class="sheet-sweep" aria-hidden="true" />

            <span class="reg reg-tl" aria-hidden="true" />
            <span class="reg reg-tr" aria-hidden="true" />
            <span class="reg reg-bl" aria-hidden="true" />

            <div class="sheet-body">
                <Link href="/" class="brand">
                    <svg class="brand-mark" viewBox="0 0 34 28" aria-hidden="true">
                        <rect x="0" y="16" width="6" height="12" />
                        <rect x="9" y="8" width="6" height="20" />
                        <rect x="18" y="0" width="6" height="28" />
                        <rect x="27" y="12" width="6" height="16" opacity="0.45" />
                    </svg>
                    <span class="brand-text">
                        <strong>PROSER</strong>
                        <em>Grupo Constructor</em>
                    </span>
                </Link>

                <div class="sheet-copy">
                    <p class="eyebrow">Departamento de TI</p>
                    <h1 class="display">
                        Sistema de gestión de<br />
                        tecnologías de la<br />
                        <span class="display-mark">información</span>
                    </h1>
                    <p class="lede">
                        Activos, documentación técnica y usuarios del departamento en un solo
                        registro, siempre al día.
                    </p>
                </div>

                <dl class="titleblock">
                    <div>
                        <dt>Hoja</dt>
                        <dd>{{ sheet }}</dd>
                    </div>
                    <div>
                        <dt>Revisión</dt>
                        <dd>{{ revision }}</dd>
                    </div>
                    <div>
                        <dt>Acceso</dt>
                        <dd>Interno</dd>
                    </div>
                </dl>
            </div>
        </section>

        <!-- Formulario -->
        <section class="panel">
            <div class="panel-inner">
                <slot />
            </div>
        </section>
    </div>
</template>

<style scoped>
.auth-shell {
    --graphite: #0d1216;
    --graphite-soft: #161d23;
    --hivis: #ffb320;
    --steel: #8d9ba6;
    --concrete: #f3f2ef;
    --ink: #14181c;

    display: grid;
    grid-template-columns: 1fr;
    min-height: 100vh;
    min-height: 100dvh;
    background: var(--concrete);
    color: var(--ink);
}

@media (min-width: 900px) {
    .auth-shell {
        grid-template-columns: 1.15fr 1fr;
    }
}

/* ---------- Hoja técnica ---------- */

.sheet {
    position: relative;
    overflow: hidden;
    display: flex;
    background: radial-gradient(120% 90% at 8% 0%, var(--graphite-soft), var(--graphite) 65%);
    color: #fff;
    padding: 2rem 1.5rem;
}

@media (min-width: 900px) {
    .sheet {
        padding: 3.5rem 3rem;
    }
}

.sheet-grid {
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(to right, rgba(255, 255, 255, 0.055) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(255, 255, 255, 0.055) 1px, transparent 1px),
        linear-gradient(to right, rgba(255, 255, 255, 0.11) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(255, 255, 255, 0.11) 1px, transparent 1px);
    background-size:
        28px 28px,
        28px 28px,
        140px 140px,
        140px 140px;
    mask-image: radial-gradient(130% 100% at 20% 15%, #000 35%, transparent 85%);
}

/* Barrido único de nivelación al cargar */
.sheet-sweep {
    position: absolute;
    inset-inline: 0;
    top: 0;
    height: 2px;
    background: linear-gradient(90deg, transparent, var(--hivis), transparent);
    opacity: 0.7;
    animation: sweep 1.6s cubic-bezier(0.16, 1, 0.3, 1) 0.2s both;
}

@keyframes sweep {
    from {
        transform: translateY(0);
        opacity: 0;
    }
    35% {
        opacity: 0.7;
    }
    to {
        transform: translateY(100vh);
        opacity: 0;
    }
}

/* Marcas de registro, como en un plano impreso */
.reg {
    position: absolute;
    width: 16px;
    height: 16px;
    border: 1px solid var(--hivis);
    opacity: 0.55;
}

.reg-tl {
    top: 22px;
    left: 22px;
    border-right: 0;
    border-bottom: 0;
}
.reg-tr {
    top: 22px;
    right: 22px;
    border-left: 0;
    border-bottom: 0;
}
.reg-bl {
    bottom: 22px;
    left: 22px;
    border-right: 0;
    border-top: 0;
}

.sheet-body {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 2.5rem;
    width: 100%;
    max-width: 34rem;
    margin: auto;
}

.brand {
    display: inline-flex;
    align-items: center;
    gap: 0.85rem;
    width: fit-content;
    text-decoration: none;
    color: inherit;
}

.brand-mark {
    width: 34px;
    height: 28px;
    fill: var(--hivis);
}

.brand-text {
    display: flex;
    flex-direction: column;
    line-height: 1.1;
}

.brand-text strong {
    font-size: 1.15rem;
    font-weight: 700;
    letter-spacing: 0.22em;
}

.brand-text em {
    font-family: ui-monospace, 'Cascadia Mono', 'Segoe UI Mono', monospace;
    font-style: normal;
    font-size: 0.6rem;
    letter-spacing: 0.3em;
    text-transform: uppercase;
    color: var(--steel);
}

.eyebrow {
    font-family: ui-monospace, 'Cascadia Mono', 'Segoe UI Mono', monospace;
    font-size: 0.68rem;
    letter-spacing: 0.32em;
    text-transform: uppercase;
    color: var(--hivis);
    margin-bottom: 1.1rem;
}

.display {
    font-size: clamp(1.9rem, 4.2vw, 3rem);
    font-weight: 600;
    line-height: 1.08;
    letter-spacing: -0.035em;
}

.display-mark {
    position: relative;
    white-space: nowrap;
}

.display-mark::after {
    content: '';
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0.08em;
    height: 0.09em;
    background: var(--hivis);
}

.lede {
    margin-top: 1.25rem;
    max-width: 30rem;
    font-size: 0.95rem;
    line-height: 1.65;
    color: #b6c2cb;
}

/* Bloque de rótulo del plano */
.titleblock {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    border: 1px solid rgba(255, 255, 255, 0.14);
    max-width: 26rem;
}

.titleblock > div {
    padding: 0.6rem 0.85rem;
    border-right: 1px solid rgba(255, 255, 255, 0.14);
}

.titleblock > div:last-child {
    border-right: 0;
}

.titleblock dt,
.titleblock dd {
    font-family: ui-monospace, 'Cascadia Mono', 'Segoe UI Mono', monospace;
}

.titleblock dt {
    font-size: 0.58rem;
    letter-spacing: 0.24em;
    text-transform: uppercase;
    color: var(--steel);
}

.titleblock dd {
    margin-top: 0.2rem;
    font-size: 0.85rem;
    color: #fff;
}

@media (max-width: 899px) {
    .sheet-body {
        gap: 1.5rem;
    }
    .lede,
    .titleblock,
    .reg {
        display: none;
    }
}

/* ---------- Panel del formulario ---------- */

.panel {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2.5rem 1.5rem 3.5rem;
}

.panel-inner {
    width: 100%;
    max-width: 24rem;
}

@media (prefers-reduced-motion: reduce) {
    .sheet-sweep {
        animation: none;
        opacity: 0;
    }
}
</style>
