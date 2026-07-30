<script setup>
defineProps({
    message: String,
});
</script>

<template>
    <p v-if="message" class="text-sm text-destructive">{{ message }}</p>
</template>
