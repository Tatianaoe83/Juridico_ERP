<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthSplitLayout from '@/Layouts/AuthSplitLayout.vue';
import AuthCheckbox from '@/components/auth/AuthCheckbox.vue';
import AuthField from '@/components/auth/AuthField.vue';
import AuthSubmit from '@/components/auth/AuthSubmit.vue';

defineProps({
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Iniciar sesión" />

    <AuthSplitLayout sheet="SGTI-01">
        <p class="kicker">Acceso al sistema</p>
        <h2 class="title">Iniciar sesión</h2>
        <p class="sub">Usa la cuenta que te asignó el departamento de TI.</p>

        <p v-if="status" class="status">{{ status }}</p>

        <a class="sso" href="/auth/microsoft/login">
            <svg class="sso-mark" viewBox="0 0 21 21" aria-hidden="true">
                <rect x="1" y="1" width="9" height="9" fill="#f25022" />
                <rect x="11" y="1" width="9" height="9" fill="#7fba00" />
                <rect x="1" y="11" width="9" height="9" fill="#00a4ef" />
                <rect x="11" y="11" width="9" height="9" fill="#ffb900" />
            </svg>
            Entrar con Microsoft
        </a>

        <div class="split"><span>o con tu correo</span></div>

        <form class="form" @submit.prevent="submit">
            <AuthField
                id="email"
                v-model="form.email"
                label="Correo"
                type="email"
                autocomplete="username"
                :error="form.errors.email"
                autofocus
                required
            />

            <AuthField
                id="password"
                v-model="form.password"
                label="Contraseña"
                type="password"
                autocomplete="current-password"
                :error="form.errors.password"
                required
            />

            <AuthCheckbox id="remember" v-model="form.remember" label="Mantener la sesión abierta" />

            <AuthSubmit :loading="form.processing" loading-label="Entrando…">Entrar</AuthSubmit>
        </form>

        <p class="foot">
            ¿No tienes cuenta?
            <Link href="/register">Solicita una</Link>
        </p>
    </AuthSplitLayout>
</template>

<style scoped>
.kicker {
    font-family: ui-monospace, 'Cascadia Mono', 'Segoe UI Mono', monospace;
    font-size: 0.62rem;
    letter-spacing: 0.3em;
    text-transform: uppercase;
    color: #8d9ba6;
}

.title {
    margin-top: 0.6rem;
    font-size: 1.85rem;
    font-weight: 600;
    letter-spacing: -0.03em;
    color: #14181c;
}

.sub {
    margin-top: 0.5rem;
    font-size: 0.9rem;
    color: #5d6b76;
}

.status {
    margin-top: 1.25rem;
    border-left: 2px solid #ffb320;
    padding-left: 0.75rem;
    font-size: 0.85rem;
    color: #4b5661;
}

.sso {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    margin-top: 2.25rem;
    border: 1px solid #d8dee3;
    border-radius: 2px;
    padding: 0.7rem 1rem;
    font-size: 0.9rem;
    font-weight: 500;
    color: #14181c;
    text-decoration: none;
    transition: border-color 0.15s, background-color 0.15s;
}

.sso:hover {
    border-color: #14181c;
    background: #fafbfc;
}

.sso-mark {
    width: 1.05rem;
    height: 1.05rem;
}

.split {
    display: flex;
    align-items: center;
    gap: 0.9rem;
    margin-top: 1.75rem;
    font-size: 0.72rem;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #8d9ba6;
}

.split::before,
.split::after {
    content: '';
    flex: 1;
    height: 1px;
    background: #e4e9ed;
}

.form {
    display: flex;
    flex-direction: column;
    gap: 1.4rem;
    margin-top: 1.75rem;
}

.foot {
    margin-top: 1.75rem;
    font-size: 0.85rem;
    color: #5d6b76;
}

.foot a {
    color: #14181c;
    text-decoration: underline;
    text-underline-offset: 3px;
    text-decoration-color: #ffb320;
    text-decoration-thickness: 2px;
}
</style>
