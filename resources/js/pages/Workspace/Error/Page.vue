<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Page } from '@kit/ui';
defineProps<Tey.Kit.Features.SessionUi.ViewModels.ErrorViewModel>();
</script>
<template>
    <Page xs>
        <p role="alert">{{ message }}</p>
        <Link :href="recovery_url">{{ recovery_label }}</Link>
    </Page>
</template>
