<script setup lang="ts">
import { type HTMLAttributes } from 'vue';
import { cn } from '@/components/Kit/utils';

/*
 * Page-level columns: 12 columns from `md`, one column below it (every cell stacks, in source order). Cells are PageGridItem (`span`, 1 to 12) or any element,
 * which spans all 12. A `preset` sizes the cells for you: `main-aside` (the first cell 8 columns, the rest 4), `main-aside-wide` (7 and 5, a wider aside), `halves` (6 each) or `thirds` (4 each). The gap is
 * the Page's (`gap-6`, both ways); cells carry no margins and shrink below their content (`min-w-0`), so a wide table scrolls inside its cell. Out-of-flow
 * children (absolute, fixed, hidden) take no cell and no gap.
 *
 * The lookups are STATIC: Tailwind finds class names by scanning source text, so whole class strings only.
 */
type PageGridPreset = 'main-aside' | 'main-aside-wide' | 'halves' | 'thirds';

const props = withDefaults(
    defineProps<{
        preset?: PageGridPreset;
        class?: HTMLAttributes['class'];
    }>(),
    { preset: undefined, class: undefined },
);
const presets: Record<PageGridPreset, string> = {
    'main-aside': 'md:*:col-span-4 md:[&>*:first-child]:col-span-8',
    'main-aside-wide': 'md:*:col-span-5 md:[&>*:first-child]:col-span-7',
    halves: 'md:*:col-span-6',
    thirds: 'md:*:col-span-4',
};
</script>
<template>
    <div
        :class="
            cn(
                'grid grid-cols-1 gap-6 *:min-w-0 md:grid-cols-12',
                props.preset ? presets[props.preset] : 'md:*:col-span-12',
                props.class,
            )
        "
        :data-preset="props.preset"
        data-slot="page-grid"
    >
        <slot />
    </div>
</template>
