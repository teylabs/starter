<script setup lang="ts">
import { Primitive, type PrimitiveProps } from 'reka-ui';
import { type HTMLAttributes } from 'vue';
import { cn } from '@/components/Kit/utils';

// Vertical rhythm between sibling blocks (cards, sections, a heading and its list): spacing has one owner, and it is this gap. `flex flex-col gap-4`; pass `class="gap-6"` for a deliberate
// different rhythm. Vertical only: a row of controls is a Toolbar, a page-level layout of columns is a PageGrid. The gap separates direct children, so a block never carries its own
// margin (no `mt-*`, `mb-*`, `space-y-*` on siblings); a wrapper holding several blocks nests its own Stack. Out-of-flow children (absolute, fixed, hidden) take no gap.
const props = withDefaults(
    defineProps<PrimitiveProps & { class?: HTMLAttributes['class'] }>(),
    { as: 'div', class: undefined },
);
</script>
<template>
    <Primitive
        :as="props.as"
        :as-child="props.asChild"
        :class="cn('flex flex-col gap-4', props.class)"
        data-slot="stack"
        ><slot
    /></Primitive>
</template>
