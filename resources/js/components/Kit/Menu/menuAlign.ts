export type MenuAlign = 'start' | 'end';

/** The preferred edge while the menu fits the viewport that way, else the other edge if that fits, else the preferred edge. */
export function alignThatFits(preferred: MenuAlign, triggerRect: { left: number; right: number }, menuWidth: number, viewportWidth: number): MenuAlign {
    const fits = (edge: MenuAlign) => (edge === 'start' ? triggerRect.left + menuWidth <= viewportWidth : triggerRect.right - menuWidth >= 0);
    const other: MenuAlign = preferred === 'start' ? 'end' : 'start';

    return fits(preferred) ? preferred : fits(other) ? other : preferred;
}
