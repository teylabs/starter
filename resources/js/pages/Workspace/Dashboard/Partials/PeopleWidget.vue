<script setup lang="ts">
import { ScrollList } from '@kit/ui';
import type { WidgetFrame } from './types';

// The workspace's people, in a ScrollList over the deferred list prop the widget names. The list's region is named apart from the card's section ("People list"
// inside "People"), so the two landmarks never share a name (axe landmark-unique).
defineProps<{ widget: WidgetFrame & { list: string } }>();
</script>
<template>
    <ScrollList :list-key="widget.list" :title="`${widget.title} list`" density="compact" class="[--scroll-list-height:18rem]" />
</template>
