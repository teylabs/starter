// Where a stage-anchored FloatingToolbar goes: centred over the selection's bounding box, above it by default, below when there is no room above, and kept
// inside the stage at every edge. Pure: FloatingToolbar measures the rects and applies the result. Rects are in one coordinate space (the viewport's, from
// getBoundingClientRect); the result is relative to the stage's top-left corner, before the stage's own scroll.
export type AnchorRect = {
    top: number;
    left: number;
    width: number;
    height: number;
};
export type ToolbarAnchor = {
    top: number;
    left: number;
    placement: 'top' | 'bottom';
};

/** The bounding box of several rects (the selected elements); null for none. */
export function unionRect(rects: AnchorRect[]): AnchorRect | null {
    if (!rects.length) return null;
    const top = Math.min(...rects.map((rect) => rect.top));
    const left = Math.min(...rects.map((rect) => rect.left));
    const bottom = Math.max(...rects.map((rect) => rect.top + rect.height));
    const right = Math.max(...rects.map((rect) => rect.left + rect.width));
    return { top, left, width: right - left, height: bottom - top };
}

const clamp = (value: number, min: number, max: number) =>
    Math.min(Math.max(value, min), Math.max(min, max));

/**
 * `gap` sits between the selection and the toolbar, `inset` between the toolbar and the stage's edges. Above when the toolbar fits between the stage's top and the
 * selection, else below; when it fits neither way it is clamped inside the stage (over the selection). A toolbar wider than the stage sits at its left inset.
 */
export function anchorToolbar(
    selection: AnchorRect,
    stage: AnchorRect,
    toolbar: { width: number; height: number },
    { gap = 8, inset = 8 } = {},
): ToolbarAnchor {
    const top = selection.top - stage.top;
    const bottom = top + selection.height;
    const centre = selection.left - stage.left + selection.width / 2;
    const left = clamp(
        centre - toolbar.width / 2,
        inset,
        stage.width - inset - toolbar.width,
    );
    const above = top - gap - toolbar.height;
    if (above >= inset) return { top: above, left, placement: 'top' };
    const below = bottom + gap;
    return {
        top: clamp(below, inset, stage.height - inset - toolbar.height),
        left,
        placement: 'bottom',
    };
}
