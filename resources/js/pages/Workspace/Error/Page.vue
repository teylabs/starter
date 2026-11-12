<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { inject } from 'vue';
import { Page, Stack } from '@kit/ui';
defineProps<Tey.Kit.Features.SessionUi.ViewModels.ErrorViewModel>();
// In the shell the Page draws the title as the page's h1; a guest page outside the shell shows none, so the error names itself here.
const inShell = inject('kit.shell', false);
</script>
<template>
    <Page xs>
        <Stack class="items-start gap-0">
            <h1 v-if="!inShell" class="text-xl font-medium" data-error-title>{{ title }}</h1>
            <p role="alert">{{ message }}</p>
            <Link :href="recovery_url">{{ recovery_label }}</Link>
        </Stack>
    </Page>
</template>
