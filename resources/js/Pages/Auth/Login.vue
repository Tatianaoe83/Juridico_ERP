<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AuthSplitLayout from '@/Layouts/AuthSplitLayout.vue';
import AuthCheckbox from '@/components/auth/AuthCheckbox.vue';
import AuthField from '@/components/auth/AuthField.vue';
import AuthSubmit from '@/components/auth/AuthSubmit.vue';

defineProps({
    status: String,
});

// Rechazos que llegan por redirección, p. ej. una cuenta de Microsoft sin alta.
const flashError = computed(() => usePage().props.flash?.error);

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

    <AuthSplitLayout>
        <div class="grid gap-1.5">
            <h1 class="text-2xl font-bold text-brand sm:text-3xl dark:text-foreground">Bienvenido de nuevo</h1>
            <p class="text-sm text-muted-foreground">Ingresa tus datos para continuar.</p>
        </div>

        <p v-if="status" role="status" class="mt-6 rounded-lg border bg-muted/50 px-3 py-2.5 text-sm">
            {{ status }}
        </p>

        <p
            v-if="flashError"
            role="alert"
            class="mt-6 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2.5 text-sm text-destructive"
        >
            {{ flashError }}
        </p>

        <form class="mt-8 grid gap-5 short:mt-6 short:gap-4" novalidate @submit.prevent="submit">
            <AuthField
                id="email"
                v-model="form.email"
                label="Correo"
                type="email"
                placeholder="nombre@proser.com.mx"
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
                placeholder="Ingresa tu contraseña"
                autocomplete="current-password"
                :error="form.errors.password"
                required
            />

            <AuthCheckbox id="remember" v-model="form.remember" label="Mantener la sesión abierta" />

            <AuthSubmit :loading="form.processing" loading-label="Entrando…">Entrar</AuthSubmit>
        </form>
    </AuthSplitLayout>
</template>
