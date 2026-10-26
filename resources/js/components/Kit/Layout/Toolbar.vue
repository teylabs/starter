<script setup lang="ts">
import { Primitive, type PrimitiveProps } from 'reka-ui';
import { computed, type HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

// Inline controls (a link, a badge, a count, a select, a Button or a button group) in one row: it owns the gap and the alignment, and reflows (`overflow="wrap"`, the default) or scrolls
// (`overflow="scroll"`, one line). `layout="heading"` is a stretched column below `lg` and a right-aligned row from `lg`. It never styles its children's appearance, never measures and
// adds no toolbar role or keyboard handling: controls keep their own tab order.
const props = withDefaults(defineProps<PrimitiveProps & { overflow?: 'wrap' | 'scroll'; layout?: 'inline' | 'heading'; class?: HTMLAttributes['class'] }>(), { as: 'div', overflow: 'wrap', layout: 'inline', class: undefined });
const classes = computed(() => cn(
    'flex min-w-0 gap-x-2 gap-y-2 sm:gap-x-3 sm:gap-y-3',
    props.overflow === 'scroll' ? 'flex-nowrap overflow-x-auto *:shrink-0' : 'flex-wrap',
    props.layout === 'heading' ? 'w-full flex-col items-stretch lg:w-auto lg:flex-row lg:items-center lg:justify-end' : 'items-center',
    props.class,
));
</script>
<template>
    <Primitive :as="props.as" :as-child="props.asChild" :class="classes" data-slot="toolbar"><slot /></Primitive>
</template>
