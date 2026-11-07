// Client-side toolbar actions: a key and a label (an icon, a disabled reason) with no URL, run by the host in the browser (bold, align, bring forward). The
// host's handler arrives as FloatingToolbar's `onAction` prop and FloatingToolbar's own click handler calls it: an item never emits from a component that may
// unmount before the call (an unmounted component's emit is dropped), the same rule as ActionBar's menu rows.
// `group` names the item's group ('text', 'block', 'slide'): consecutive items of one group sit together, with a separator between groups. `iconOnly` shows only
// the icon: the label becomes the button's accessible name and its tooltip (title).
export type ToolbarAction = {
    key: string;
    label: string;
    icon?: string | null;
    disabled?: boolean;
    reason?: string | null;
    group?: string | null;
    iconOnly?: boolean;
};

/** Hands a chosen item to the host's handler: never a disabled item, never without a handler. True when the handler ran. */
export function runToolbarAction(
    handler: ((action: ToolbarAction) => void) | undefined,
    action: ToolbarAction,
): boolean {
    if (!handler || action.disabled) return false;
    handler(action);
    return true;
}

/** A menu row's item, handed to the host after the menu has closed (and unmounted the row): the handler is captured now, as ActionBar's menu rows do (deferSelect). */
export function deferToolbarAction(
    handler: ((action: ToolbarAction) => void) | undefined,
    action: ToolbarAction,
    schedule: (run: () => void) => unknown = (run) => setTimeout(run, 0),
): void {
    if (!handler || action.disabled) return;
    schedule(() => runToolbarAction(handler, action));
}

/** Consecutive items of one group, in order. Items with no group form groups of their own kind, so ungrouped items stay one run. */
export function toolbarGroups(items: ToolbarAction[]): ToolbarAction[][] {
    const groups: ToolbarAction[][] = [];
    for (const item of items) {
        const last = groups.at(-1);
        if (last && (last[0].group ?? null) === (item.group ?? null))
            last.push(item);
        else groups.push([item]);
    }
    return groups;
}

export type ToolbarFit = {
    overflowing: boolean;
    inline: ToolbarAction[];
    menu: ToolbarAction[];
};
/** `container`: the width the items have; `widths`: each item's natural width, in order; `gap` between items, `separator` a group separator's width (with its gaps), `trigger` the ⌄ button's. */
export type ToolbarFitMeasure = {
    container: number;
    widths: number[];
    gap?: number;
    separator?: number;
    trigger?: number;
};

/** The slack an overflowing toolbar needs before it shows every item again, so a width at the boundary cannot flip it back and forth. */
export const TOOLBAR_FIT_HYSTERESIS = 8;

/**
 * Which items fit inline, and which go behind the ⌄ menu. Everything fits: all inline. Otherwise whole trailing groups move into the menu first, from the last;
 * when only the first group is left and it still does not fit, its trailing items follow, so the first N stay (N may be 0: the menu alone). An unmeasured toolbar
 * (zero width, or widths not yet read) shows everything. One that `wasOverflowing` shows everything again only with TOOLBAR_FIT_HYSTERESIS to spare.
 */
export function fitToolbarItems(
    items: ToolbarAction[],
    {
        container,
        widths,
        gap = 0,
        separator = 0,
        trigger = 0,
    }: ToolbarFitMeasure,
    wasOverflowing = false,
): ToolbarFit {
    const all = {
        overflowing: false,
        inline: [...items],
        menu: [] as ToolbarAction[],
    };
    if (!items.length || !(container > 0) || widths.length !== items.length)
        return all;
    const width = new Map(
        items.map((item, index) => [item, Math.max(0, widths[index])]),
    );
    const needed = (inline: ToolbarAction[][], menu: boolean) => {
        const count = inline.reduce((sum, group) => sum + group.length, 0);
        const own = inline
            .flat()
            .reduce((sum, item) => sum + (width.get(item) ?? 0), 0);
        const separators =
            Math.max(0, inline.filter((group) => group.length).length - 1) *
            Math.max(0, separator);
        return (
            own +
            separators +
            Math.max(0, count - 1) * gap +
            (menu ? (count ? gap : 0) + trigger : 0)
        );
    };
    const groups = toolbarGroups(items);
    if (
        needed(groups, false) <=
        container + 0.5 - (wasOverflowing ? TOOLBAR_FIT_HYSTERESIS : 0)
    )
        return all;
    const split = (inline: ToolbarAction[]) => ({
        overflowing: true,
        inline,
        menu: items.slice(inline.length),
    });
    for (let kept = groups.length - 1; kept >= 1; kept--) {
        if (needed(groups.slice(0, kept), true) <= container + 0.5)
            return split(groups.slice(0, kept).flat());
    }
    for (let kept = groups[0].length - 1; kept >= 0; kept--) {
        if (needed([groups[0].slice(0, kept)], true) <= container + 0.5)
            return split(groups[0].slice(0, kept));
    }
    return split([]);
}

/** Whether two measures are the same, so a resize that changes nothing the fit reads is skipped. */
export function sameToolbarMeasure(
    a: ToolbarFitMeasure | null,
    b: ToolbarFitMeasure,
): boolean {
    return (
        a !== null &&
        a.container === b.container &&
        (a.gap ?? 0) === (b.gap ?? 0) &&
        (a.separator ?? 0) === (b.separator ?? 0) &&
        (a.trigger ?? 0) === (b.trigger ?? 0) &&
        a.widths.length === b.widths.length &&
        a.widths.every((width, index) => width === b.widths[index])
    );
}
