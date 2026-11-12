<script setup lang="ts">
import Card from './Card.vue';
import CardBody from './CardBody.vue';
import CardHeader from './CardHeader.vue';
import { hasSlotContent } from './slotContent';

/*
 * A plain panel with one decision in it (docs/cards.md; SummaryCard before v0.16), built from the card parts: no header band, the title (a heading at `level`, 3 by
 * default) and its `description` (or #description), capped at max-w-xl, on the left, and #actions on the right. `align` places the actions: `center`
 * (default) centres them on the text, `top` pins them top-right. On a phone the actions stack under the text. More content goes in the default slot, below.
 * `tone="danger"` (a destructive step: delete, leave, revoke) gives it a red edge and a red title and keeps the card's own background: no tint, so the
 * description and whatever button goes in #actions keep the contrast they have on any card. Use a quiet button with red text there (Button
 * `variant="secondary"` with `class="text-destructive-text"`), and save the filled red for the confirm dialog.
 */
withDefaults(
    defineProps<{
        title: string;
        description?: string | null;
        level?: 1 | 2 | 3 | 4 | 5 | 6;
        tone?: 'default' | 'danger';
        align?: 'center' | 'top';
    }>(),
    { description: null, level: 3, tone: 'default', align: 'center' },
);
</script>
<template>
    <Card
        :class="tone === 'danger' && 'border-destructive/50'"
        data-action-card
        :data-action-card-tone="tone"
        :data-align="align"
    >
        <CardHeader
            :title="title"
            :level="level"
            :actions-align="align === 'top' ? 'start' : 'center'"
            stack-actions
            :class="
                tone === 'danger' ? 'text-destructive-text' : 'text-foreground'
            "
        >
            <template
                v-if="
                    $slots.description
                        ? hasSlotContent($slots.description)
                        : description
                "
                #meta
                ><p class="max-w-xl" data-action-card-description>
                    <slot name="description">{{ description }}</slot>
                </p></template
            >
            <template v-if="hasSlotContent($slots.actions)" #actions
                ><div
                    class="flex flex-wrap items-center gap-2"
                    data-action-card-actions
                >
                    <slot name="actions" /></div
            ></template>
        </CardHeader>
        <CardBody v-if="hasSlotContent($slots.default)" data-action-card-body
            ><slot
        /></CardBody>
    </Card>
</template>
