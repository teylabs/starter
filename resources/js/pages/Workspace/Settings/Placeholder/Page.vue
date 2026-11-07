<script setup lang="ts">
import { Page, Placeholder } from '@kit/ui';

// A planned settings section: the placeholder inside the settings layout, which supplies the width.
defineProps<Tey.Kit.Features.SessionUi.ViewModels.PlaceholderViewModel>();
// Named, not inline: vue-tsc 2 can't parse an inline object type in a template expression (TS1005).
type PlaceholderLink = { label: string; url: string };
</script>
<template>
    <Page md>
        <Placeholder :description="description" :milestone="milestone" :links="(links as unknown) as PlaceholderLink[]" />
    </Page>
</template>
