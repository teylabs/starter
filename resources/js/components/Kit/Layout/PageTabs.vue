<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed, inject, onBeforeUnmount } from 'vue';
import InputSelect from '@/components/Kit/Form/InputSelect.vue';

// Tabs that are pages: each is a route link in a `<nav>` landmark, the current one marked `aria-current="page"`. Phones get the kit's InputSelect, which visits the chosen page.
// For panes inside one page use PaneTabs instead. `current` is the href of the page shown; when it is omitted, the tab flagged `current` wins.
// Drawn inside a <Page>, these tabs are the page's: the <Page> then draws no `layout.navigation` tabs of its own, so there is one row.
const release = inject<(() => () => void) | null>('kit.page.tabs', null)?.();
if (release) onBeforeUnmount(release);
const props = defineProps<{
    tabs: { href: string; label: string; current?: boolean }[];
    label: string;
    current?: string;
}>();
const active = computed(
    () => props.current ?? props.tabs.find((tab) => tab.current)?.href ?? null,
);
const options = computed(() =>
    props.tabs.map((tab) => ({ value: tab.href, label: tab.label })),
);
// A tab focused by keyboard is brought wholly into view: browsers leave a partly visible one where it is, cut off at the row's edge.
const reveal = (event: FocusEvent) => {
    if (event.target instanceof HTMLElement)
        event.target.scrollIntoView({ block: 'nearest', inline: 'nearest' });
};
const go = (href: unknown) => {
    if (typeof href === 'string' && href !== active.value) router.visit(href);
};
</script>
<template>
    <nav :aria-label="label" data-page-tabs>
        <div class="sm:hidden">
            <InputSelect
                :aria-label="label"
                :model-value="active"
                :options="options"
                :clearable="false"
                @update:model-value="go"
            />
        </div>
        <!-- Scrolls sideways only when the tabs don't fit, never up and down, with no visible scrollbar (kit.css hides it). The baseline is an inset shadow, not a border the links
             overlap with -mb-px (that overlap overflowed the row by a pixel, and overflow-x forces overflow-y to scroll); the focus ring is inset so the row can't clip it. -->
        <ul
            class="hidden gap-6 overflow-x-auto overflow-y-hidden overscroll-x-contain shadow-[inset_0_-1px_0_var(--color-border)] sm:flex"
            @focusin="reveal"
        >
            <li v-for="tab in tabs" :key="tab.href" class="shrink-0">
                <Link
                    :href="tab.href"
                    :aria-current="tab.href === active ? 'page' : undefined"
                    :class="[
                        'focus-visible:outline-ring inline-flex border-b-2 px-1 py-3 text-sm font-medium whitespace-nowrap focus-visible:outline-2 focus-visible:-outline-offset-2',
                        tab.href === active
                            ? 'border-primary text-foreground'
                            : 'text-muted-foreground hover:border-border hover:text-foreground border-transparent',
                    ]"
                    >{{ tab.label }}</Link
                >
            </li>
        </ul>
    </nav>
</template>
