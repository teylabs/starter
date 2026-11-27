<script setup lang="ts">
import { Button, EmptyState, Page } from '@kit/ui';
import WidgetGrid from './Partials/WidgetGrid.vue';
import type { WidgetFrame } from './Partials/types';
// The workspace home. The greeting is the page heading; below it the widget grid (`Registry::widget()`), or the empty state when the viewer has no widgets. The
// viewer's own onboarding steps are in the You card and the workspace's in the "Set up this workspace" card, last in the aside. An app adds widgets rather than
// forking this page, and its header actions with `Registry::dashboardActions()`; it can still replace it by registering its own dashboard component, which receives the same props and can render <WidgetGrid> where it likes.
defineProps<Tey.Kit.Features.SessionUi.ViewModels.WorkspaceDashboardViewModel>();
</script>
<template>
    <Page :title="greeting" :actions="actions">
    <WidgetGrid v-if="widgets.length" :widgets="widgets as WidgetFrame[]" :aside="aside" />
    <EmptyState v-else :level="2" variant="first-use" icon="activity" title="No widgets yet" :body="`This is the home of ${workspace}. What your team works on will show up here.`" class="max-w-xl" data-dashboard-empty>
        <template #action><Button :href="next_url">{{ next_label }}</Button></template>
    </EmptyState>
    </Page>
</template>
