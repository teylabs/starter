<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronRight, House } from '@lucide/vue';
import { computed } from 'vue';

/*
 * The trail to the page, a small line above its heading (as GOV.UK and Polaris place it): the page's ancestors from its `layout.breadcrumbs` (gretel's trail, mapped
 * once on the server), the current page left out because its heading names it. The root is a home icon named by the workspace (or the product). A page with no
 * ancestors (the home page itself) keeps the line's height as an aria-hidden spacer, so a heading sits at the same height on every page. <Page> renders it in the
 * shell; a page without <Page> renders it itself, above its own heading. `crumbs` overrides the page's trail.
 * `bar` is the shell's full-width trail bar (the Layout's `breadcrumbs="bar"`): the whole trail, the current page included as its last crumb (a link to itself,
 * marked aria-current and in the foreground colour), and no spacer, since the bar keeps its own height.
 */
type Crumb = {
    label: string;
    url: string | null;
    home: boolean;
    current: boolean;
    title?: string | null;
};
const props = defineProps<{ crumbs?: Crumb[]; bar?: boolean }>();
const page = usePage<{ layout?: { breadcrumbs?: Crumb[] } }>();
const ancestors = computed(() =>
    (props.crumbs ?? page.props.layout?.breadcrumbs ?? []).filter(
        (crumb) => props.bar || !crumb.current,
    ),
);
</script>
<template>
    <nav
        v-if="ancestors.length"
        aria-label="Breadcrumb"
        :class="
            bar
                ? 'flex min-w-0 flex-1 items-center overflow-hidden'
                : 'flex h-5 min-w-0 items-center overflow-hidden'
        "
        :data-slot="bar ? 'breadcrumbs-bar' : 'breadcrumbs'"
    >
        <ol
            :class="[
                'flex min-w-0 items-center gap-1 text-sm whitespace-nowrap',
                bar ? 'text-foreground/80' : 'text-muted-foreground',
            ]"
        >
            <li
                v-for="(crumb, index) in ancestors"
                :key="index"
                :class="[
                    'flex items-center gap-1',
                    crumb.home ? 'shrink-0' : 'min-w-0',
                ]"
            >
                <ChevronRight
                    v-if="index > 0"
                    class="size-3.5 shrink-0"
                    aria-hidden="true"
                />
                <Link
                    v-if="crumb.home"
                    :href="crumb.url ?? '#'"
                    :aria-label="crumb.label"
                    :aria-current="crumb.current ? 'page' : undefined"
                    class="hover:text-foreground focus-visible:ring-ring inline-flex size-5 items-center justify-center rounded-md focus-visible:ring-2 focus-visible:outline-none"
                    data-breadcrumb-home
                    ><House class="size-4" aria-hidden="true"
                /></Link>
                <Link
                    v-else
                    :href="crumb.url ?? '#'"
                    :aria-current="crumb.current ? 'page' : undefined"
                    :class="crumb.current && 'text-foreground'"
                    class="hover:text-foreground focus-visible:ring-ring block max-w-48 min-w-0 truncate rounded-sm hover:underline focus-visible:ring-2 focus-visible:outline-none"
                    :title="crumb.title || crumb.label"
                    >{{ crumb.label }}</Link
                >
            </li>
        </ol>
    </nav>
    <div
        v-else-if="!bar"
        aria-hidden="true"
        class="h-5"
        data-slot="breadcrumbs-spacer"
    />
</template>
