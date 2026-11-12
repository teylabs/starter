<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Page } from '@kit/ui';
import WidgetGrid from './Partials/WidgetGrid.vue';
import type { WidgetFrame } from './Partials/types';
// The workspace home. The greeting is the page heading; below it the widget grid (`Registry::widget()`), or the empty state when the viewer has no widgets. The
// viewer's own onboarding steps are in the You card and the workspace's in the "Set up this workspace" card, last in the aside. An app adds widgets rather than
// forking this page; it can still replace it by registering its own dashboard component, which receives the same props and can render <WidgetGrid> where it likes.
defineProps<Tey.Kit.Features.SessionUi.ViewModels.WorkspaceDashboardViewModel>();
</script>
<template>
    <Page :title="greeting">
    <WidgetGrid v-if="widgets.length" :widgets="widgets as WidgetFrame[]" :aside="aside" />
    <section v-else class="grid max-w-xl justify-items-start gap-3 rounded-xl border border-dashed p-8" data-dashboard-empty>
        <h2 class="text-lg font-semibold">Nothing here yet</h2>
        <p class="text-sm text-muted-foreground">This is the home of {{ workspace }}. What your team works on will show up here.</p>
        <Link :href="next_url" class="mt-2 inline-flex min-h-11 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">{{ next_label }}</Link>
    </section>
    </Page>
</template>
