<script setup lang="ts">
import { computed, type HTMLAttributes } from 'vue';
import { Primitive, type PrimitiveProps } from 'reka-ui';
import { cn } from '@/components/Kit/utils';

/*
 * One cell of a PageGrid: `span` columns of 12 from `md` (with no `span`, the grid's preset decides, else the whole row); below `md` every cell is the full width. It stacks its own content
 * with a Stack's rhythm (`gap-4`) and carries no margin. The lookup is STATIC (whole class strings only), and `!` wins over the grid's preset.
 */
type Span = 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 | 10 | 11 | 12;

const props = withDefaults(
    defineProps<
        PrimitiveProps & { span?: Span; class?: HTMLAttributes['class'] }
    >(),
    { as: 'div', span: undefined, class: undefined },
);
const spans: Record<Span, string> = {
    1: 'md:col-span-1!',
    2: 'md:col-span-2!',
    3: 'md:col-span-3!',
    4: 'md:col-span-4!',
    5: 'md:col-span-5!',
    6: 'md:col-span-6!',
    7: 'md:col-span-7!',
    8: 'md:col-span-8!',
    9: 'md:col-span-9!',
    10: 'md:col-span-10!',
    11: 'md:col-span-11!',
    12: 'md:col-span-12!',
};
const spanClass = computed(() => (props.span ? spans[props.span] : undefined));
</script>
<template>
    <Primitive
        :as="props.as"
        :as-child="props.asChild"
        :class="cn('flex min-w-0 flex-col gap-4', spanClass, props.class)"
        :data-span="props.span"
        data-slot="page-grid-item"
        ><slot
    /></Primitive>
</template>
