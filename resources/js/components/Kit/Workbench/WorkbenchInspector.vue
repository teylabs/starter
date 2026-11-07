<script setup lang="ts">
import { computed } from 'vue';
import { useWorkbench } from './useWorkbench';

/*
 * The inspector region's default content: it follows the stage's selection and renders the registered inspector for it (useWorkbench().defineInspector).
 * Nothing selected → the `@document` inspector (the whole document's settings); one type (one item or several of it) → that type's; a mixed selection → a
 * common inspector registered for all its types, else `@fallback`. Selected stage elements (wb.selectElement) decide first, by element type, the same way.
 * Each gets `{ element, elements, ids, items, types, current }` (`element` only when one element is selected). With no inspector to show, a short note says why.
 */
const props = withDefaults(
    defineProps<{
        workbenchKey: string;
        emptyLabel?: string;
        unsupportedLabel?: string;
    }>(),
    {
        emptyLabel: 'Select something to see its details.',
        unsupportedLabel: 'There are no shared settings for this selection.',
    },
);
const wb = useWorkbench(props.workbenchKey);
const resolved = computed(() => wb.inspector);
const items = computed(() =>
    resolved.value.context.ids
        .map((id) => wb.items.find((item) => item.id === id))
        .filter((item) => !!item),
);
</script>
<template>
    <div
        class="min-w-0"
        data-workbench-inspector
        :data-inspector-kind="resolved.kind"
        :data-inspector-type="resolved.type ?? undefined"
    >
        <component
            :is="resolved.component"
            v-if="resolved.component"
            :element="resolved.context.element"
            :elements="resolved.context.elements"
            :ids="resolved.context.ids"
            :items="items"
            :types="resolved.context.types"
            :current="resolved.context.current"
        />
        <p v-else class="text-muted-foreground text-sm" data-inspector-empty>
            {{ resolved.kind === 'document' ? emptyLabel : unsupportedLabel }}
        </p>
    </div>
</template>
