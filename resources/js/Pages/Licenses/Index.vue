<script setup>
import { Head } from '@inertiajs/vue3';
import AppShell from '@/Layouts/AppShell.vue';

const breadcrumbs = [{ label: 'Inicio', href: '/calendario' }, { label: 'Licencias y permisos' }];
</script>

<template>
    <Head title="Licencias y permisos" />

    <AppShell :breadcrumbs="breadcrumbs" />
</template>
