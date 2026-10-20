<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { FocusPage } from '@kit/ui';
defineProps<Tey.Kit.Features.SessionUi.ViewModels.ErrorViewModel>();
</script>
<template>
    <FocusPage size="xs">
        <p role="alert">{{ message }}</p>
        <Link :href="recovery_url">{{ recovery_label }}</Link>
    </FocusPage>
</template>
