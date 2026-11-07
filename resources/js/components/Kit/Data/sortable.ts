import type { Method } from '@/components/Kit/types';
import { http } from '@inertiajs/vue3';
import { useDraggable } from 'vue-draggable-plus';
import {
    nextTick,
    onBeforeUnmount,
    ref,
    toValue,
    watch,
    type MaybeRefOrGetter,
    type Ref,
} from 'vue';
import { errorJson, responseJson } from '../http';
import { isConflict, type ReorderResult } from '../Workbench/workbench';

/**
 * The kit's one reorder model, shared by the Workbench navigator, the Repeater and SortableList: the order moves (moveItem), an optimistic apply that rolls back
 * on a conflict (createReorder), the app's reorder action that carries a version token (sendOrder), and the live announcement after a move. useSortable adds
 * pointer drag by a grip (vue-draggable-plus) and arrow keys on the grip, and leaves the order to its host: it only calls `move(from, to)`.
 */

// The pure reorder steps stay in the Workbench's helper module (loaded on its own by its tests); re-exported here as the shared model.
export {
    moveItem,
    isConflict,
    createReorder,
    type ReorderResult,
} from '../Workbench/workbench';

/** The app's reorder action: it takes `{ order, version }` and answers `{ version }`. */
export type ReorderAction = { url: string; method: string };

/**
 * Sends the new order to the app's reorder action as `{ order, version }`; a 2xx answers `{ version }`, a 409 or a stale/conflict code is a conflict. The body is
 * read through responseJson (Inertia v3's client gives it as a string), so the new token is kept for the next write; a 2xx without a JSON body is a failed save.
 */
export async function sendOrder(
    action: ReorderAction,
    order: string[],
    version: string | number | null,
): Promise<ReorderResult> {
    try {
        const response = await http.getClient().request({
            method: action.method.toLowerCase() as Method,
            url: action.url,
            data: { order, version },
            headers: { Accept: 'application/json' },
        });
        if (response.status >= 200 && response.status < 300) {
            const data = responseJson(response) as {
                version?: string | number | null;
            } | null;
            return { ok: true, version: data?.version ?? null };
        }
        const refused = errorJson({ response });
        return {
            ok: false,
            conflict: isConflict(
                response.status,
                (refused.body as { error?: string } | null)?.error,
            ),
        };
    } catch (error) {
        const refused = errorJson(error);
        return {
            ok: false,
            conflict: isConflict(
                refused.status,
                (refused.body as { error?: string } | null)?.error,
            ),
        };
    }
}

/** What a polite live region says after a move: "Slide 3 moved to position 2 of 8". */
export function moveAnnouncement(
    label: string,
    to: number,
    total: number,
): string {
    return `${label} moved to position ${to + 1} of ${total}`;
}

/** The arrow-key move on a focused grip: −1, +1, to the start (Home) or the end (End), or null for any other key. Alt is left to the Workbench's own keys. */
export function gripMove(
    event: Pick<KeyboardEvent, 'key' | 'altKey' | 'metaKey' | 'ctrlKey'>,
    index: number,
    total: number,
): number | null {
    if (event.altKey || event.metaKey || event.ctrlKey) return null;
    if (event.key === 'ArrowUp' || event.key === 'ArrowLeft')
        return index > 0 ? index - 1 : null;
    if (event.key === 'ArrowDown' || event.key === 'ArrowRight')
        return index < total - 1 ? index + 1 : null;
    if (event.key === 'Home') return index > 0 ? 0 : null;
    if (event.key === 'End') return index < total - 1 ? total - 1 : null;
    return null;
}

export type UseSortableOptions = {
    /** The ids in their current order (the host's; useSortable never keeps its own). */
    ids: MaybeRefOrGetter<string[]>;
    /** Applies a move; the host changes its order (and may roll it back). */
    move: (from: number, to: number) => void | Promise<unknown>;
    /** The rows that drag (default `[data-sortable-item]`) and the grip inside each (default `[data-sortable-handle]`). */
    itemSelector?: string;
    handleSelector?: string;
    /** Off: no drag and no grip keys (a filter is on, the form is locked, a save is in flight). */
    disabled?: MaybeRefOrGetter<boolean>;
    /** The element that holds the rows, when the host already has a ref to it; else bind the returned `container`. */
    container?: Ref<HTMLElement | null | undefined>;
};

/**
 * Pointer drag by a grip, and the grip's arrow keys, over a container's rows. Bind `container` to the element that holds the rows, and call `onGripKeydown(index, event)`
 * from each grip (SortableHandle does). After a keyboard move the grip of the moved row keeps focus.
 */
export function useSortable(options: UseSortableOptions) {
    const container = (options.container ??
        ref<HTMLElement | null>(null)) as Ref<HTMLElement | null | undefined>;
    const itemSelector = options.itemSelector ?? '[data-sortable-item]';
    const handleSelector = options.handleSelector ?? '[data-sortable-handle]';
    // The drag library reorders this copy (and puts the DOM back); the host's order is reapplied at once, so the host stays the source of truth.
    const shadow = ref<string[]>([...toValue(options.ids)]) as Ref<string[]>;
    watch(
        () => toValue(options.ids),
        (ids) => {
            shadow.value = [...ids];
        },
    );
    const draggable = useDraggable(container, shadow, {
        handle: handleSelector,
        draggable: itemSelector,
        animation: 150,
        ghostClass: 'opacity-40',
        disabled: !!toValue(options.disabled),
        onUpdate: (event) => {
            shadow.value = [...toValue(options.ids)];
            const from = event.oldDraggableIndex ?? event.oldIndex ?? -1;
            const to = event.newDraggableIndex ?? event.newIndex ?? -1;
            if (from >= 0 && to >= 0 && from !== to)
                void options.move(from, to);
        },
    });
    watch(
        () => !!toValue(options.disabled),
        (off) => draggable.option('disabled', off),
    );
    onBeforeUnmount(() => draggable.destroy());

    async function onGripKeydown(
        index: number,
        event: KeyboardEvent,
    ): Promise<void> {
        if (toValue(options.disabled)) return;
        const to = gripMove(event, index, toValue(options.ids).length);
        if (to === null) return;
        event.preventDefault();
        const id = toValue(options.ids)[index];
        await options.move(index, to);
        await nextTick();
        const at = toValue(options.ids).indexOf(id);
        container.value
            ?.querySelectorAll<HTMLElement>(itemSelector)
            [at]?.querySelector<HTMLElement>(handleSelector)
            ?.focus();
    }

    return { container, onGripKeydown };
}
