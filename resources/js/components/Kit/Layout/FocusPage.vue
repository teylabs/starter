<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, inject, type HTMLAttributes } from 'vue';

/*
 * The page measure: a centred column at one of a few fixed widths, inside the shell. The shell owns the gutters and the vertical rhythm,
 * so this adds no padding. Use it where the content is one sequence read top to bottom (a form, a settings section, a confirmation);
 * dashboards and lists take the full width and need no wrapper.
 *
 * Inside the signed-in shell it also renders the page header, once for every page: the heading (the page's `title` prop, or the `title` prop here)
 * and an `actions` slot, in the same column as the content. `size="full"` is for fluid data pages (lists, tables, grids): no cap, so the
 * heading aligns with the full-width content.
 *
 * The lookup is STATIC and must stay that way: Tailwind finds class names by scanning source text, so a class built from the prop
 * (`max-w-${size}`) does not exist at build time and silently renders no width. Whole class strings only.
 */
type FocusPageSize = 'xs' | 'sm' | 'md' | 'lg' | 'xl' | 'full';

const props = withDefaults(defineProps<{ size?: FocusPageSize; title?: string; heading?: boolean; class?: HTMLAttributes['class'] }>(), { size: 'lg', title: undefined, heading: true });
const inShell = inject('kit.shell', false);
const page = usePage<{ title?: string }>();
const headingText = computed(() => (inShell && props.heading ? (props.title ?? page.props.title ?? '') : ''));

const maxWidths: Record<FocusPageSize, string> = {
    xs: 'max-w-2xl',
    sm: 'max-w-3xl',
    md: 'max-w-5xl',
    lg: 'max-w-7xl',
    xl: 'max-w-[96rem]',
    full: 'max-w-none',
};

const maxWidthClass = computed(() => maxWidths[props.size]);
</script>
<template>
    <div data-slot="focus-page" :class="['mx-auto w-full', maxWidthClass, props.class]">
        <div v-if="headingText || $slots.actions" class="mb-6 flex flex-wrap items-start justify-between gap-x-4 gap-y-2" data-slot="page-header">
            <h1 v-if="headingText" data-slot="page-heading" class="text-3xl font-semibold tracking-tight">{{ headingText }}</h1>
            <div v-if="$slots.actions" class="flex items-center gap-2"><slot name="actions" /></div>
        </div>
        <slot />
    </div>
</template>
