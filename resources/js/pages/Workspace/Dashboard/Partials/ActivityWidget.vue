<script setup lang="ts">
import { ActivityStream, type ActivityEntry } from '@kit/ui';
import type { WidgetFrame } from './types';

// The workspace's recent activity, newest first: who did what, and when (in the viewer's own zone and locale), drawn by the kit's ActivityStream.
defineProps<{ widget: WidgetFrame & { items: ActivityEntry[] } }>();
</script>
<template>
    <div data-widget-activity>
        <ActivityStream :entries="widget.items" :days="false" dense />
    </div>
</template>
