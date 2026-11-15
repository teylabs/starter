<script setup lang="ts">
import { CollectionCard, type CollectionSectionData } from '@kit/ui';
import { computed } from 'vue';
import type { WidgetFrame } from './types';

// The card every widget renders in: the kit's CollectionCard (a CollectionSection on a Card, docs/collection-section.md) over the widget's frame, which carries the same fields as a
// CollectionSectionData. So a widget gets its title (a link to its see-all's URL unless it opts out), its header actions, its Sort by menu (`sortBy`: a labelled
// InputSelect, saved for the viewer with a PUT that reloads only the props the widget names), its empty state or its error with Retry, and its see-all
// ("All cases →") in a footer at the bottom right, outside the body's scroll, or in the header. The body is the default slot. `headless` drops the title bar
// (the You card): the card keeps the title as its accessible name, and its body renders the actions itself. A widget's `loadingHeight` is the ready body's
// minimum height, so the card holds the space its deferred content will fill and nothing below shifts. The server also sends a widget's see-all as a header
// action for a card copied before this one (Widgets); CollectionSection leaves out the GET action with its URL, so it is drawn once. Only the frame's section fields
// are passed on: a widget's own props (which may share a name with another section field, such as `segments`) never reach the card.
const props = defineProps<{ widget: WidgetFrame; headless?: boolean }>();
const section = computed<CollectionSectionData>(() => ({
    title: props.widget.title,
    state: props.widget.state,
    empty: props.widget.empty,
    error: props.widget.error,
    actions: props.widget.actions,
    sortBy: props.widget.sortBy ?? null,
    seeAll: props.widget.seeAll ?? null,
}));
const loadingHeight = computed(() => props.widget.loadingHeight ?? null);
</script>
<template>
    <CollectionCard :section="section" :level="2" :headless="headless" :min-height="loadingHeight" :data-widget="widget.key">
        <slot />
    </CollectionCard>
</template>
