<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { computed, nextTick } from 'vue';
import { mixedIconsWarning } from '@/components/Kit/Action/actionIcons';
import { provideMenuItemClass } from '@/components/Kit/Action/menuItemClass';
import MenuPanel from '@/components/Kit/Menu/MenuPanel.vue';
import MenuTrigger from '@/components/Kit/Menu/MenuTrigger.vue';

/*
 * A row of related controls joined into one shape (docs/single-actions.md): the default slot holds the buttons (an InertiaAction per named action, or plain
 * controls), and `#menu` the rest behind a chevron trigger, as DropdownAction / DropdownItem entries in the kit's menu. Children are restyled to sit flush: one
 * outline around the segments, a divider between them, no border or radius of their own, so nothing looks nested; the menu trigger is its own outlined
 * segment beside them. A disabled segment is dimmed, hover included. Segments are semibold in the outline's text colour and dim on hover. `appearance="text"` drops the outline and
 * fill for link-style actions in a quiet header. `label` puts words beside the chevron; `menuLabel` names the trigger for assistive tech ("More actions" by
 * default). `triggerClass` is added last to the menu trigger's classes. `offset` is the menu's [skid, gap] from the trigger. Layout only: it runs nothing itself. Written clean-room on the kit's paper and outline tokens.
 */
const props = withDefaults(
    defineProps<{
        label?: string | null;
        menuLabel?: string | null;
        appearance?: 'default' | 'text';
        offset?: [number, number];
        weight?: 'medium' | 'semibold';
        itemClass?: string;
        triggerClass?: string;
    }>(),
    {
        label: null,
        menuLabel: null,
        appearance: 'default',
        offset: () => [0, 10],
        weight: 'medium',
        itemClass: undefined,
        triggerClass: undefined,
    },
);
// `itemClass`: the host's own classes for each row in #menu (its row height, say), added after the row's own; destructive and disabled still apply.
provideMenuItemClass(() => props.itemClass);

const text = computed(() => props.appearance === 'text');
// Written out in full so Tailwind sees every class: the segment look (every segment, the menu trigger included, is py-2 with a 20px line and its
// content centred, and a child's own fixed height, such as a small Button's h-8, is dropped, so the labels of both segments share one baseline), once for the menu trigger and once applied to each child of the default slot.
const trigger = computed(() =>
    text.value
        ? 'inline-flex items-center gap-1 px-2 py-1 text-sm font-medium text-primary hover:underline focus-visible:outline-2 focus-visible:outline-ring'
        : 'inline-flex items-center gap-1 rounded-md bg-paper px-2 py-2 text-sm leading-5 text-outline-foreground shadow-xs ring-1 ring-outline ring-inset hover:bg-outline-wash focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-ring dark:shadow-none',
);
// The label weight is the container's: ActionBar (a page or section header's actions) passes `semibold`; a toolbar's group keeps `medium`.
const weights = {
    medium: '[&>*]:font-medium',
    semibold: '[&>*]:font-semibold',
} as const;
const children = computed(() =>
    text.value
        ? '[&>*]:inline-flex [&>*]:items-center [&>*]:gap-1 [&>*]:bg-transparent [&>*]:px-2 [&>*]:py-1 [&>*]:text-sm [&>*]:font-medium [&>*]:text-primary [&>*:hover]:underline'
        : '[&>*]:inline-flex [&>*]:h-auto [&>*]:items-center [&>*]:gap-1.5 [&>*]:bg-transparent [&>*]:px-3 [&>*]:py-2 [&>*]:text-sm [&>*]:leading-5 [&>*]:text-outline-foreground [&>*:hover]:opacity-70 [&>*:focus-visible]:z-10',
);
// A disabled segment reads as disabled: a `disabled` child, or an `aria-disabled` one that is not the action in flight (ActionSegment keeps a disabled action
// focusable for its reason), is dimmed and keeps its dimness on hover. Important, so neither the hover dim nor a host's fill on the segment outranks it.
const disabled =
    '[&>*:disabled]:cursor-not-allowed! [&>*:disabled]:opacity-50! [&>*[aria-disabled=true]:not([aria-busy=true])]:cursor-not-allowed! [&>*[aria-disabled=true]:not([aria-busy=true])]:opacity-50!';
// The divider between segments. Not `divide-x`: Tailwind v4 sets its width under `:where()`, so the `[&>*]:border-0` reset above (which strips each
// child's own border) wins and erases it. A right border on every segment but the last, listed after the reset, outranks it.
const dividers =
    '[&>*:not(:last-child)]:border-r [&>*:not(:last-child)]:border-solid [&>*:not(:last-child)]:border-outline';
// Dev builds only: when the open menu's rows mix icons and no icons, warn once for this group (mixedIconsWarning). A row's icon is an svg among its own
// children or its link's. ActionMenuItems rows warn for themselves (their spacer marks them), so they are not counted twice.
const warnMixed = mixedIconsWarning('ButtonGroup menu');
function checkIcons(el: unknown): void {
    if (!import.meta.env?.DEV || !(el instanceof HTMLElement)) return;
    void nextTick(() => {
        if (el.querySelector('[data-menu-icon-spacer]')) return;
        warnMixed(
            [
                ...el.querySelectorAll<HTMLElement>(
                    '[role="menuitem"], [role="menuitemcheckbox"]',
                ),
            ].map((row) =>
                Boolean(row.querySelector(':scope > svg, :scope > * > svg')),
            ),
        );
    });
}
</script>
<template>
    <div
        :class="['inline-flex max-w-full items-stretch']"
        :data-appearance="appearance"
        data-button-group
        role="group"
    >
        <div
            v-if="$slots.default"
            :class="[
                'flex min-w-0 items-stretch [&>*]:min-w-0 [&>*]:truncate [&>*]:rounded-none [&>*]:border-0 [&>*]:shadow-none [&>*]:ring-0',
                !text &&
                    'bg-paper text-outline-foreground ring-outline overflow-hidden rounded-md shadow-xs ring-1 ring-inset dark:shadow-none',
                !text && $slots.menu && 'rounded-r-none',
                children,
                !text && dividers,
                !text && weights[weight],
                disabled,
            ]"
        >
            <slot />
        </div>
        <div v-if="$slots.menu" class="relative block">
            <MenuPanel
                flip-align
                :side-offset="offset[1]"
                :skid="offset[0]"
                content-class="z-50 w-64 overflow-hidden rounded-md border-0 bg-popover p-0 text-popover-foreground shadow-lg dark:ring-1 dark:ring-white/10"
            >
                <template #trigger>
                    <MenuTrigger>
                        <button
                            type="button"
                            :aria-label="
                                label
                                    ? undefined
                                    : (menuLabel ?? 'More actions')
                            "
                            :class="[
                                trigger,
                                !text &&
                                    $slots.default &&
                                    '-ml-px rounded-l-none',
                                triggerClass,
                            ]"
                            data-button-group-menu
                        >
                            <span v-if="$slots.label || label"
                                ><slot name="label">{{ label }}</slot></span
                            >
                            <ChevronDown
                                aria-hidden="true"
                                :class="
                                    text
                                        ? 'size-4'
                                        : 'text-outline-muted size-5'
                                "
                            />
                        </button>
                    </MenuTrigger>
                </template>
                <div
                    v-if="$slots.menuheader"
                    class="border-border border-b px-4 py-3 text-wrap"
                >
                    <slot name="menuheader" />
                </div>
                <div :ref="checkIcons" class="py-1"><slot name="menu" /></div>
            </MenuPanel>
        </div>
    </div>
</template>
