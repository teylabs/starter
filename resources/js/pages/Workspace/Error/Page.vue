<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Page, Stack } from '@kit/ui';
import { computed, inject, type Ref } from 'vue';
defineProps<Tey.Kit.Features.SessionUi.ViewModels.ErrorViewModel>();
// The title is the page's one h1. In the app shell <Page> draws it (the layout's title); in a GuestShell given the title it is the shell's. Anywhere else
// (a guest's error outside the app shell: the PublicShell, a bare GuestShell, a host's own layout) nothing draws it, so the page does.
const inShell = inject('kit.shell', false);
const shellTitled = inject<Ref<boolean> | null>('kit.shell.titled', null);
const ownHeading = computed(() => !inShell && !shellTitled?.value);
</script>
<template>
    <Page xs>
        <h1 v-if="ownHeading" class="font-display text-3xl font-(weight:--font-display-weight) tracking-tight" data-error-heading>{{ title }}</h1>
        <Stack class="items-start gap-0">
            <p role="alert">{{ message }}</p>
            <Link :href="recovery_url">{{ recovery_label }}</Link>
        </Stack>
    </Page>
</template>
