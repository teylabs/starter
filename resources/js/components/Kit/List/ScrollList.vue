<script setup lang="ts" generic="T extends ListItem = ListItem">
import { InfiniteScroll } from '@inertiajs/vue3';
import Alert from '@/components/Kit/Feedback/Alert.vue';
import Button from '@/components/Kit/Form/Button.vue';
import ScrollListRow from './ScrollListRow.vue';
import { useScrollList, type ListItem } from './useScrollList';

// A scrolling list that loads more as the viewer scrolls (Inertia's infinite scroll), over a server `ScrollListViewModel` prop named `listKey`. A labelled, focusable region
// with a maximum height (`--scroll-list-height`, 24rem by default); rows come from `ListItemData`, drawn by the slim default row or by the `row` slot ({ item }) for a richer one.
// A "Load more" button is always present while there is more: it is the keyboard and screen-reader path, and auto-loading never moves focus. A failed load keeps the loaded rows
// and offers Retry. Cursor-only and count-free: no sorts, segments, totals or polling.
const props = withDefaults(defineProps<{ listKey: string; title: string; density?: 'default' | 'compact' }>(), { density: 'default' });
const { list, items, stream, region, mounted, generation, loading, error, announcement, fetchNext, refresh, request } = useScrollList<T>(props.listKey);
</script>
<template>
    <div ref="region" role="region" tabindex="0" :aria-label="title" :aria-busy="loading || !list || undefined" class="max-h-(--scroll-list-height,24rem) min-h-0 overflow-y-auto overscroll-contain focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring" :data-scroll-list="listKey" :data-density="density">
        <div v-if="!list" role="status" class="grid gap-3 motion-safe:animate-pulse"><span class="sr-only">Loading {{ title }}</span><div v-for="line in 3" :key="line" class="h-16 rounded-md bg-muted" /></div>
        <Alert v-else-if="list.state === 'error' && !items.length" variant="warning" :title="list.error?.message ?? `This couldn't load.`"><template #actions><Button type="button" variant="secondary" @click="refresh">{{ list.error?.retry?.label ?? 'Retry' }}</Button></template></Alert>
        <div v-else-if="list.state === 'empty' && list.empty" class="grid justify-items-start gap-1 py-6" data-scroll-list-empty :data-variant="list.empty.variant">
            <p class="font-medium">{{ list.empty.title }}</p>
            <p class="text-sm text-muted-foreground">{{ list.empty.description }}</p>
            <Button v-if="list.empty.variant === 'no-results' && list.empty.action" type="button" variant="secondary" @click="refresh">{{ list.empty.action.label }}</Button>
        </div>
        <InfiniteScroll v-else-if="mounted && list" :key="generation" ref="stream" :data="listKey" only-next preserve-url :manual="error || list.state === 'error'" :buffer="80" :params="request" :items-element="`[data-scroll-list='${listKey}'] ul`">
            <ul class="grid">
                <li v-for="(item, index) in items" :key="item.id" tabindex="-1" data-list-item :data-list-row="item.id" class="grid gap-3 border-t border-border first:border-0" :class="density === 'compact' ? 'py-2' : 'py-3 first:pt-0 last:pb-0'">
                    <h3 v-if="item.group && item.group.key !== items[index - 1]?.group?.key" class="text-sm font-semibold">{{ item.group.label }}</h3>
                    <slot name="row" :item="item"><ScrollListRow :item="item" /></slot>
                </li>
            </ul>
            <template #next="{ hasNext, loadingNext }">
                <div v-if="hasNext || error" class="grid justify-items-center gap-2 py-3">
                    <p v-if="error" role="alert" class="text-sm text-muted-foreground">Couldn't load more. Retry.</p>
                    <Button v-if="hasNext" type="button" variant="secondary" data-load-more :disabled="loadingNext || loading" @click="fetchNext">{{ loadingNext ? 'Loading…' : error ? 'Retry' : 'Load more' }}</Button>
                </div>
            </template>
        </InfiniteScroll>
        <p aria-live="polite" aria-atomic="true" class="sr-only">{{ announcement }}</p>
    </div>
</template>
