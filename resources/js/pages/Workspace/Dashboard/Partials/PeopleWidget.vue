<script setup lang="ts">
import { ScrollList } from '@kit/ui';
import type { WidgetFrame } from './types';

// The workspace's people, in a ScrollList over the deferred list prop the widget names.
defineProps<{ widget: WidgetFrame & { list: string } }>();
</script>
<template>
    <ScrollList :list-key="widget.list" :title="widget.title" density="compact" style="--scroll-list-height: 18rem" />
</template>
