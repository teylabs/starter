<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import {
    computed,
    defineComponent,
    getCurrentInstance,
    inject,
    provide,
    ref,
    type Ref,
} from 'vue';
import HeaderActions from '@/components/Kit/Action/HeaderActions.vue';
import HeaderStatus from '@/components/Kit/Action/HeaderStatus.vue';
import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';
import type { HeaderStatus as HeaderStatusValue } from '@/components/Kit/Action/headerSplit';
import Breadcrumbs from './Breadcrumbs.vue';
import FocusPage from './FocusPage.vue';
import PageTabs from './PageTabs.vue';

/*
 * The root of every page: in the shell, the trail to it (Breadcrumbs, a small line above the heading, drawn whether or not there is a heading; not when the shell
 * draws the trail as a bar, and not again by a <Page> nested in another), then the header
 * (title, status and actions), then the content, in one column.
 * Tabs: in the shell, a top-level <Page> draws the page's `layout.navigation` (decided on the server: a module's sibling pages from Registry::modulePage(),
 * or a record's sections its ViewModel sets) as PageTabs at its top, above the heading, the current one marked. It draws none when a nested <Page>, when a
 * frame draws the navigation itself (SettingsFrame's sidebar), when the page renders its own <PageTabs> anywhere inside it (a record frame placing its tabs
 * below its header: that PageTabs claims them, so there is one row), or when the Layout keeps the deprecated SubNav strip (`subNav="strip"`).
 * Header actions: `actions` (server descriptors) render through HeaderActions, split by headerSplit into a filled primary, up to two secondaries and a More menu
 * (only the primary below `sm`); three or fewer with no declared `priority` render through ActionBar exactly as the same descriptors in the slot would. A
 * non-navigating action is emitted as `action` when the page listens, else run (confirmation included). The `actions` slot is the escape hatch: whatever it holds
 * renders as is, after them, with no rule applied. Status: the `status` slot, or the `status` prop ({ state, label }), sits before the actions in a muted polite
 * live region (role="status"), never a button.
 * the width is a boolean prop (`<Page sm>`: `xs`, `sm`, `md`, `lg`, `xl`, or `full`); `full` or no prop is the whole shell, for lists, tables and grids. A page whose content does not
 * grow with data (a form, a settings section, a detail or confirmation page, an empty state, an account or guest page) declares a focused width.
 * The title is the page's `layout.title` (the same decision as the breadcrumb and the document title) unless `title` is passed; guest pages outside the shell
 * show none. `state` swaps the content for the `loading`, `empty` or `error` slot (each has a plain default).
 * Rhythm: the page owns the vertical spacing between its visible direct children (the head, then each block of content) with one gap (`gap-6`), so no child
 * carries an outer margin (`mt-*`, `mb-*`, `space-y-*`). Out-of-flow children (absolute, fixed, a dialog or a portal, hidden) take no gap. `flush` turns the
 * gap off, for a page whose content is one full-bleed child (a workbench, a split pane) that lays itself out. Columns are a PageGrid; a titled region a PageSection.
 */
type PageState = 'ready' | 'loading' | 'empty' | 'error';

const props = withDefaults(
    defineProps<{
        xs?: boolean;
        sm?: boolean;
        md?: boolean;
        lg?: boolean;
        xl?: boolean;
        full?: boolean;
        flush?: boolean;
        title?: string;
        heading?: boolean;
        state?: PageState;
        actions?: ActionDescriptor[];
        status?: HeaderStatusValue | null;
    }>(),
    {
        title: undefined,
        heading: true,
        state: 'ready',
        actions: () => [],
        status: null,
    },
);
const emit = defineEmits<{ action: [action: ActionDescriptor] }>();
// Forwarded only when the page listens, so HeaderActions (and ActionBar under it) runs the action itself otherwise.
const instance = getCurrentInstance();
const listeners = computed(() =>
    instance?.vnode.props?.onAction
        ? { onAction: (action: ActionDescriptor) => emit('action', action) }
        : {},
);
const inShell = inject('kit.shell', false);
// The trail is drawn once: not when the shell draws it as a bar (the Layout's `breadcrumbs="bar"`), and not by a <Page> inside another (a tabbed frame round each
// tab), which the outer one already drew. A page with a "Back to <parent>" strip (`layout.navigationHeader`: a breakout page, or an account page) has no trail: the strip replaces it.
const shellTrail = inject<Ref<'page' | 'bar'> | null>(
    'kit.shell.breadcrumbs',
    null,
);
const nested = inject('kit.page', false);
provide('kit.page', true);
// The page's tabs (`layout.navigation`): drawn here unless something inside draws them (a <PageTabs> claims them through the outermost <Page>).
const claimed = ref(false);
const outerClaim = inject<(() => void) | null>('kit.page.tabs', null);
provide(
    'kit.page.tabs',
    nested && outerClaim
        ? outerClaim
        : () => {
              claimed.value = true;
          },
);
// The page's own tabs claim nothing: they render inside this, which hides the claim.
const OwnTabs = defineComponent({
    setup(_, { slots }) {
        provide('kit.page.tabs', null);
        return () => slots.default?.();
    },
});
const framed = inject('kit.navigation.framed', false);
const subNav = inject<Ref<'tabs' | 'strip'> | null>('kit.shell.subnav', null);
const page = usePage<{
    layout?: {
        title: string;
        navigation?: {
            label: string | null;
            items: { label: string; url: string; current: boolean }[];
        }[];
        navigationHeader?: { label: string; url: string } | null;
    };
    title?: string;
}>();
const drawsTrail = computed(
    () =>
        inShell &&
        !nested &&
        shellTrail?.value !== 'bar' &&
        !page.props.layout?.navigationHeader,
);
const tabs = computed(() =>
    inShell && !nested && !framed && !claimed.value && subNav?.value !== 'strip'
        ? (page.props.layout?.navigation ?? []).flatMap((group) =>
              group.items.map((item) => ({
                  href: item.url,
                  label: item.label,
                  current: item.current,
              })),
          )
        : [],
);
const tabsLabel = computed(
    () =>
        page.props.layout?.navigation?.find((group) => group.label)?.label ??
        'Sections',
);
const headingText = computed(() =>
    inShell && props.heading
        ? (props.title ?? page.props.layout?.title ?? page.props.title ?? '')
        : '',
);
// One width at most: more than one is a mistake, warned about in development. The first focused width given wins; `full`, or none, is the whole shell.
if (
    import.meta.env?.DEV &&
    [props.xs, props.sm, props.md, props.lg, props.xl, props.full].filter(
        Boolean,
    ).length > 1
) {
    console.warn('<Page>: set one width at most (xs, sm, md, lg, xl or full).');
}
const size = computed(
    () =>
        (['xs', 'sm', 'md', 'lg', 'xl'] as const).find((name) => props[name]) ??
        'full',
);
</script>
<template>
    <FocusPage
        :size="size"
        :class="flush ? undefined : 'flex flex-col gap-6'"
        :data-flush="flush || undefined"
        data-slot="page"
    >
        <OwnTabs v-if="tabs.length"
            ><PageTabs :tabs="tabs" :label="tabsLabel" data-page-module-tabs
        /></OwnTabs>
        <div
            v-if="
                drawsTrail ||
                headingText ||
                $slots.actions ||
                $slots.status ||
                status ||
                actions.length
            "
            class="flex flex-col gap-2"
            data-slot="page-head"
        >
            <Breadcrumbs v-if="drawsTrail" />
            <div
                v-if="
                    headingText ||
                    $slots.actions ||
                    $slots.status ||
                    status ||
                    actions.length
                "
                class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2"
                data-slot="page-header"
            >
                <h1
                    v-if="headingText"
                    data-slot="page-heading"
                    class="font-display text-3xl font-(weight:--font-display-weight) tracking-tight"
                >
                    {{ headingText }}
                </h1>
                <div
                    v-if="
                        $slots.actions ||
                        $slots.status ||
                        status ||
                        actions.length
                    "
                    class="flex items-center gap-2"
                >
                    <HeaderStatus v-if="$slots.status"
                        ><slot name="status" /></HeaderStatus
                    ><HeaderStatus
                        v-else-if="status"
                        :status="status"
                    /><HeaderActions
                        v-if="actions.length"
                        :actions="actions"
                        v-bind="listeners"
                    /><slot name="actions" />
                </div>
            </div>
        </div>
        <div
            v-if="state === 'loading'"
            role="status"
            aria-live="polite"
            data-slot="page-loading"
        >
            <slot name="loading"
                ><p class="text-muted-foreground text-sm">Loading…</p></slot
            >
        </div>
        <div v-else-if="state === 'empty'" data-slot="page-empty">
            <slot name="empty"
                ><p class="text-muted-foreground text-sm">
                    Nothing here yet.
                </p></slot
            >
        </div>
        <div v-else-if="state === 'error'" role="alert" data-slot="page-error">
            <slot name="error"
                ><p class="text-destructive-text text-sm">
                    Something went wrong.
                </p></slot
            >
        </div>
        <slot v-else />
    </FocusPage>
</template>
