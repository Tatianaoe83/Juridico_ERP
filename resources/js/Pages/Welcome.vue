<script setup>
import { Head } from '@inertiajs/vue3';
import { Rocket } from 'lucide-vue-next';
import { cn } from '@/lib/utils';

defineProps({
    message: String,
});
</script>

<template>
    <Head title="Welcome" />

    <div class="flex min-h-screen items-center justify-center bg-gray-50">
        <div :class="cn('flex items-center gap-3 rounded-lg bg-white px-6 py-4 shadow')">
            <Rocket class="h-6 w-6 text-indigo-600" />
            <span class="text-lg font-medium text-gray-900">{{ message }}</span>
        </div>
    </div>
</template>
