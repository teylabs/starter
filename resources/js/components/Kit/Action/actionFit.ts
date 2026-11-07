// Whether an ActionBar's full layout fits the width it has, or falls back to its main action and a ⌄ menu with every other action, in order. Pure: ActionBar
// measures its own root (a ResizeObserver, never the viewport) and the natural widths of its full layout's parts, and asks this.
export type ActionFit<T> = { compact: boolean; inline: T[]; menu: T[] };
export type ActionFitMeasure = {
    container: number;
    parts: number[];
    gap?: number;
};

/** The slack a compact bar needs before it returns to its full layout, so a width at the boundary cannot flip it back and forth. */
export const ACTION_FIT_HYSTERESIS = 8;

/**
 * `container` is the bar's own width; `parts` the natural widths of its full layout's groups; `gap` the space between them. One action or none never compacts; nor
 * does an unmeasured bar (zero width: server render, a hidden tab), so it shows everything until it has a width. Half a pixel of rounding is forgiven. A bar that
 * `wasCompact` stays compact until the full layout fits with ACTION_FIT_HYSTERESIS to spare.
 */
export function fitActions<T>(
    actions: T[],
    { container, parts, gap = 0 }: ActionFitMeasure,
    wasCompact = false,
): ActionFit<T> {
    const full = { compact: false, inline: [...actions], menu: [] as T[] };
    if (actions.length <= 1 || !(container > 0)) return full;
    const needed =
        parts.reduce((sum, width) => sum + Math.max(0, width), 0) +
        Math.max(0, parts.length - 1) * Math.max(0, gap);
    if (needed <= container + 0.5 - (wasCompact ? ACTION_FIT_HYSTERESIS : 0))
        return full;
    return {
        compact: true,
        inline: actions.slice(0, 1),
        menu: actions.slice(1),
    };
}

/** Whether two measures are the same, so a resize that changes nothing the fit reads is skipped. */
export function sameMeasure(
    a: ActionFitMeasure | null,
    b: ActionFitMeasure,
): boolean {
    return (
        a !== null &&
        a.container === b.container &&
        (a.gap ?? 0) === (b.gap ?? 0) &&
        a.parts.length === b.parts.length &&
        a.parts.every((width, index) => width === b.parts[index])
    );
}
