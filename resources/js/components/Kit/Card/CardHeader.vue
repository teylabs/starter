<script setup lang="ts">
import { computed } from 'vue';
import { CARD_GUTTER, CARD_TONES, type CardTone } from './cardTone';

/*
 * A card's header (docs/cards.md): the title as a real heading at `level` (3 by default; the page decides the hierarchy) from `title` or the default slot (a
 * link), a `#meta` line under it (a source, a seller, a time; links allowed), an optional `#avatar` before it and `#leading` before everything (a drag grip),
 * and on the other side `#status` and `#actions` (a ButtonGroup of named actions). `tone` colours it as a band; `unstyled` drops the padding, rule and type so
 * the host's classes rule, with every slot still working. `align` places `#leading`, `#avatar` and the title column against each other: `center` (default)
 * centres them across the title and meta, `start` puts them at the top, and `baseline` lines up text in `#leading` or `#avatar` (an index such as "5.")
 * with the title's first line.
 */
const props = withDefaults(
    defineProps<{
        title?: string | null;
        level?: 1 | 2 | 3 | 4 | 5 | 6;
        tone?: CardTone | null;
        unstyled?: boolean;
        align?: 'center' | 'start' | 'baseline';
    }>(),
    { title: null, level: 3, tone: null, unstyled: false, align: 'center' },
);
const heading = computed(() => `h${props.level}`);
// Whole class strings, never built from the prop (Tailwind finds classes by scanning the source).
const aligns = {
    center: 'items-center',
    start: 'items-start',
    baseline: 'items-baseline',
} as const;
</script>
<template>
    <header
        :class="[!unstyled && [CARD_GUTTER, 'py-4'], tone && CARD_TONES[tone]]"
        data-card-header
        :data-tone="tone ?? undefined"
    >
        <div
            class="flex min-w-0 flex-wrap items-center justify-between gap-x-3 gap-y-2"
        >
            <div :class="['flex min-w-0 gap-x-3', aligns[align]]">
                <slot name="leading" />
                <div v-if="$slots.avatar" class="shrink-0">
                    <slot name="avatar" />
                </div>
                <div class="grid min-w-0 gap-0.5">
                    <component
                        :is="heading"
                        :class="!unstyled && 'truncate text-base font-semibold'"
                        ><slot>{{ title }}</slot></component
                    >
                    <div
                        v-if="$slots.meta"
                        :class="
                            !unstyled && [
                                'text-sm',
                                tone ? 'opacity-80' : 'text-muted-foreground',
                            ]
                        "
                        data-card-meta
                    >
                        <slot name="meta" />
                    </div>
                </div>
            </div>
            <div
                v-if="$slots.status || $slots.actions"
                class="flex min-w-0 flex-wrap items-center justify-end gap-3"
            >
                <slot name="status" />
                <slot name="actions" />
            </div>
        </div>
    </header>
</template>
