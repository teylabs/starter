<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, inject } from 'vue';
import FocusPage from './FocusPage.vue';

/*
 * The root of every page, rendered below the shell's breadcrumb sub-bar: the header (title and an `actions` slot), then the content, in one column.
 * the width is a boolean prop (`<Page sm>`: `xs`, `sm`, `md`, `lg`, `xl`, or `full`); `full` or no prop is the whole shell, for lists, tables and grids. A page whose content does not
 * grow with data (a form, a settings section, a detail or confirmation page, an empty state, an account or guest page) declares a focused width.
 * The title is the page's `layout.title` (the same decision as the breadcrumb and the document title) unless `title` is passed; guest pages outside the shell
 * show none. `state` swaps the content for the `loading`, `empty` or `error` slot (each has a plain default).
 */
type PageState = 'ready' | 'loading' | 'empty' | 'error';

const props = withDefaults(defineProps<{ xs?: boolean; sm?: boolean; md?: boolean; lg?: boolean; xl?: boolean; full?: boolean; title?: string; heading?: boolean; state?: PageState }>(), { title: undefined, heading: true, state: 'ready' });
const inShell = inject('kit.shell', false);
const page = usePage<{ layout?: { title: string }; title?: string }>();
const headingText = computed(() => (inShell && props.heading ? (props.title ?? page.props.layout?.title ?? page.props.title ?? '') : ''));
// One width at most: more than one is a mistake, warned about in development. The first focused width given wins; `full`, or none, is the whole shell.
if (import.meta.env?.DEV && [props.xs, props.sm, props.md, props.lg, props.xl, props.full].filter(Boolean).length > 1) {
    console.warn('<Page>: set one width at most (xs, sm, md, lg, xl or full).');
}
const size = computed(() => (['xs', 'sm', 'md', 'lg', 'xl'] as const).find((name) => props[name]) ?? 'full');
</script>
<template>
    <FocusPage :size="size" data-slot="page">
        <div v-if="headingText || $slots.actions" class="mb-6 flex flex-wrap items-start justify-between gap-x-4 gap-y-2" data-slot="page-header">
            <h1 v-if="headingText" data-slot="page-heading" class="font-serif text-3xl font-(weight:--font-display-weight) tracking-tight">{{ headingText }}</h1>
            <div v-if="$slots.actions" class="flex items-center gap-2"><slot name="actions" /></div>
        </div>
        <div v-if="state === 'loading'" role="status" aria-live="polite" data-slot="page-loading"><slot name="loading"><p class="text-sm text-muted-foreground">Loading…</p></slot></div>
        <div v-else-if="state === 'empty'" data-slot="page-empty"><slot name="empty"><p class="text-sm text-muted-foreground">Nothing here yet.</p></slot></div>
        <div v-else-if="state === 'error'" role="alert" data-slot="page-error"><slot name="error"><p class="text-sm text-destructive">Something went wrong.</p></slot></div>
        <slot v-else />
    </FocusPage>
</template>
