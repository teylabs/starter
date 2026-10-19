<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import WorkspaceLayout from '../layouts/WorkspaceLayout.vue';
defineProps<{ status: number; message: string; recovery_url: string; recovery_label: string }>();
</script>
<template><WorkspaceLayout :title="`${status} · Request could not be completed`"><p role="alert">{{ message }}</p><Link :href="recovery_url">{{ recovery_label }}</Link></WorkspaceLayout></template>
