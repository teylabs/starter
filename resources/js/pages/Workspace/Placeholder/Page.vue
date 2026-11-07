<script setup lang="ts">
import { Page, Placeholder } from '@kit/ui';

// A page that is not built yet, in the real page's width. Making it real is a controller for the same route.
defineProps<Tey.Kit.Features.SessionUi.ViewModels.PlaceholderViewModel>();
// Named, not inline: vue-tsc 2 can't parse an inline object type in a template expression (TS1005).
type PlaceholderLink = { label: string; url: string };
</script>
<template>
    <Page :xs="width === 'xs'" :sm="width === 'sm'" :md="width === 'md'" :lg="width === 'lg'" :xl="width === 'xl'" :full="width === 'full' || width === null">
        <Placeholder :description="description" :milestone="milestone" :links="(links as unknown) as PlaceholderLink[]" />
    </Page>
</template>
