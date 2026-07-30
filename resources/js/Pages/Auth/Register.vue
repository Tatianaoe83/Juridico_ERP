<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthSplitLayout from '@/Layouts/AuthSplitLayout.vue';
import AuthField from '@/components/auth/AuthField.vue';
import AuthSubmit from '@/components/auth/AuthSubmit.vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Crear cuenta" />

    <AuthSplitLayout sheet="SGTI-02">
        <p class="kicker">Alta de usuario</p>
        <h2 class="title">Crear cuenta</h2>
        <p class="sub">Registra tus datos para acceder al sistema.</p>

        <form class="form" @submit.prevent="submit">
            <AuthField
                id="name"
                v-model="form.name"
                label="Nombre"
                autocomplete="name"
                :error="form.errors.name"
                autofocus
                required
            />

            <AuthField
                id="email"
                v-model="form.email"
                label="Correo"
                type="email"
                autocomplete="username"
                :error="form.errors.email"
                required
            />

            <AuthField
                id="password"
                v-model="form.password"
                label="Contraseña"
                hint="mín. 8 caracteres"
                type="password"
                autocomplete="new-password"
                :error="form.errors.password"
                required
            />

            <AuthField
                id="password_confirmation"
                v-model="form.password_confirmation"
                label="Confirmar contraseña"
                type="password"
                autocomplete="new-password"
                required
            />

            <AuthSubmit :loading="form.processing" loading-label="Creando…">Crear cuenta</AuthSubmit>
        </form>

        <p class="foot">
            ¿Ya tienes cuenta?
            <Link href="/login">Entrar</Link>
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

.form {
    display: flex;
    flex-direction: column;
    gap: 1.4rem;
    margin-top: 2.25rem;
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
