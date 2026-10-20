<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
defineProps<Tey.Kit.Features.SessionUi.ViewModels.ErrorViewModel>();
</script>
<template>
    <p role="alert">{{ message }}</p>
    <Link :href="recovery_url">{{ recovery_label }}</Link>
</template>
