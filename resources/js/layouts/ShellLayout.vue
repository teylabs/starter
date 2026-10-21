<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import FocusLayout from '@/layouts/FocusLayout.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';

// The server picks the shell per route and names it in `shell.kind`; this only renders that one.
defineOptions({ inheritAttrs: false });

const page = usePage<{ shell?: { kind?: 'app' | 'guest' | 'focus' } }>();
</script>

<template>
    <FocusLayout v-if="page.props.shell?.kind === 'focus'"><slot /></FocusLayout>
    <GuestLayout v-else-if="page.props.shell?.kind === 'guest'"><slot /></GuestLayout>
    <AppLayout v-else><slot /></AppLayout>
</template>
