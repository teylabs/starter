<script setup lang="ts">
import {
    computed,
    onBeforeUnmount,
    onMounted,
    ref,
    type HTMLAttributes,
} from 'vue';
import { cn } from '@/components/Kit/utils';
import { useStickyWhenFits } from './useStickyWhenFits';

/*
 * A page's aside column, usually the last cell of a PageGrid: an <aside> named by `label` that spaces its sections with the page's gap (`gap-6`), so two
 * stacked feeds never touch. Each section is a PageSection (its own title and heading level). From `md` it sticks below the shell header only while all of it
 * fits the viewport (it takes its own height, never the row's, so the measure is its content); taller, it stays in the flow and scrolls with the page, so the page keeps one scrollbar. `sticky: false` never sticks.
 */
const props = withDefaults(
    defineProps<{
        label: string;
        sticky?: boolean;
        class?: HTMLAttributes['class'];
    }>(),
    { sticky: true, class: undefined },
);
// Always measured, so `sticky` can change after mount; it sticks only from `md` (where the class applies), so `data-sticky` is what a reader sees.
const { el, sticky: fits } = useStickyWhenFits(true);
const wide = ref(false);
let query: MediaQueryList | undefined;
const onQuery = () => (wide.value = !!query?.matches);
onMounted(() => {
    query = window.matchMedia('(min-width: 48rem)');
    onQuery();
    query.addEventListener('change', onQuery);
});
onBeforeUnmount(() => query?.removeEventListener('change', onQuery));
const stuck = computed(() => props.sticky && fits.value && wide.value);
</script>
<template>
    <aside
        ref="el"
        :aria-label="label"
        :class="
            cn(
                'grid min-w-0 content-start gap-6 self-start',
                stuck && 'md:sticky md:top-[7.5rem]',
                props.class,
            )
        "
        :data-sticky="stuck ? 'true' : 'false'"
        data-slot="page-aside"
    >
        <slot />
    </aside>
</template>
