<script setup lang="ts">
import { Page, Placeholder } from '@kit/ui';

// A planned settings section: the placeholder inside the settings layout, which supplies the width.
defineProps<Tey.Kit.Features.SessionUi.ViewModels.PlaceholderViewModel>();
</script>
<template>
    <Page md>
        <Placeholder :description="description" :milestone="milestone" :links="(links as unknown) as { label: string; url: string }[]" />
    </Page>
</template>
