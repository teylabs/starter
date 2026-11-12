<script setup lang="ts">
import { Checklist, type ChecklistItem } from '@kit/ui';
import type { WidgetFrame } from './types';

// "Set up this workspace": the workspace's own onboarding steps, each linking to its welcome step. The server leaves the card out once every one is done.
defineProps<{ widget: WidgetFrame & { checklist: ChecklistItem[] } }>();
</script>
<template>
    <div data-widget-setup>
        <Checklist :items="widget.checklist" label="Workspace setup steps" :announce="false" />
    </div>
</template>
