<script setup lang="ts">
import { PageGrid, Stack } from '@kit/ui';
import type { Component } from 'vue';
import { computed } from 'vue';
import ActivityWidget from './ActivityWidget.vue';
import PeopleWidget from './PeopleWidget.vue';
import SetupWidget from './SetupWidget.vue';
import WidgetCard from './WidgetCard.vue';
import YouWidget from './YouWidget.vue';
import type { WidgetFrame } from './types';

// The dashboard's two columns: a PageGrid `main-aside` (8 and 4 of 12 from `md`, one column below it; `main-aside-wide`, 7 and 5, when `aside` is `wide`), each column a Stack of its widgets' cards in order. Below `md`
// the main column's cards come first, then the aside's. Each card spans its column; `span` no longer drives the layout. With only one column in use it takes the
// full width. A widget's `component` picks its body: the kit's own, or `resources/js/widgets/{Name}.vue` in the app (`queue` renders `widgets/Queue.vue`). An
// unknown component renders the card with no body. The kit's You card has no title bar.
const props = withDefaults(defineProps<{ widgets: WidgetFrame[]; aside?: 'narrow' | 'wide' }>(), { aside: 'narrow' });
const own = import.meta.glob<{ default: Component }>('/resources/js/widgets/*.vue', { eager: true });
const kit: Record<string, Component> = { activity: ActivityWidget, people: PeopleWidget, setup: SetupWidget, you: YouWidget };
const body = (name: string): Component | undefined => kit[name] ?? own[`/resources/js/widgets/${name.charAt(0).toUpperCase()}${name.slice(1)}.vue`]?.default;
// A frame without a region (an app's own older payload) stacks in main.
const columns = computed(() => {
    const main = props.widgets.filter((widget) => widget.region !== 'aside');
    const aside = props.widgets.filter((widget) => widget.region === 'aside');
    return [
        { region: 'main', widgets: main },
        { region: 'aside', widgets: aside },
    ].filter((column) => column.widgets.length > 0);
});
</script>
<template>
    <PageGrid :preset="columns.length > 1 ? (aside === 'wide' ? 'main-aside-wide' : 'main-aside') : undefined" data-widget-grid>
        <Stack v-for="column in columns" :key="column.region" :data-widget-region="column.region">
            <WidgetCard v-for="widget in column.widgets" :key="widget.key" :widget="widget" :headless="widget.component === 'you'">
                <component :is="body(widget.component)" v-if="body(widget.component)" :widget="widget" />
            </WidgetCard>
        </Stack>
    </PageGrid>
</template>
