import type { ActionDescriptor } from './actionPlacement';

/*
 * A chosen menu row's action, run after the menu has closed and given focus back (opened in the same tick, the menu's own dismissal would swallow the host's
 * confirmation). The handler is captured when the row is chosen: by the time the timer fires the menu content (and the rows in it) has unmounted, and an emit
 * from an unmounted component is dropped, so the action must not depend on the row still being there.
 */
export function deferSelect(
    handler: ((action: ActionDescriptor) => void) | undefined,
    action: ActionDescriptor,
    schedule: (run: () => void) => unknown = (run) => setTimeout(run, 0),
): void {
    if (!handler) return;
    schedule(() => handler(action));
}
