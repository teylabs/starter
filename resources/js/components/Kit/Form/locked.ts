// The locked state of a form area (the LockedForm spec), kept free of Vue so it can be tested on its own: the server decides (a `null` save descriptor locks),
// a locked ancestor cannot be unlocked by a descendant, and the notice is the plural sentence, the singular one with `quantity="one"`, or the caller's own.
export type LockedState = { locked: boolean; notice: string; reason: string | null };
export type LockedProps = { save: object | null; quantity?: 'many' | 'one'; notice?: string; reason?: string | null };

export const NOTICES = { many: 'You can view these settings but not change them.', one: 'You can view this setting but not change it.' } as const;
/** Outside any LockedForm: editable, the plural notice, no reason. */
export const UNLOCKED: LockedState = { locked: false, notice: NOTICES.many, reason: null };
/** The pill's label when the locked area gives no reason. */
export const VIEW_ONLY = 'View only';

/** The state a LockedForm provides: its own `save` (null locks) or any locked ancestor; its own notice and reason. */
export function resolveLocked(props: LockedProps, parent: LockedState = UNLOCKED): LockedState {
    return { locked: props.save === null || parent.locked, notice: props.notice ?? NOTICES[props.quantity ?? 'many'], reason: props.reason ?? null };
}

/** The read-only label in a FloatingToolbar's pill: the reason when there is one, else "View only". */
export function readOnlyLabel(state: LockedState): string {
    return state.reason ?? VIEW_ONLY;
}

/** Descriptor actions a locked area shows: everything but `intent: 'edit'`. Unlocked, the list is unchanged. */
export function withoutEditActions<T extends { intent?: string | null }>(actions: T[], locked: boolean): T[] {
    return locked ? actions.filter(action => action.intent !== 'edit') : actions;
}

/** Whether a key press on an element would submit its form implicitly: Enter in a single-line input (not a textarea, button or link). */
export function implicitSubmit(key: string, tag: string, type: string | null = null): boolean {
    return key === 'Enter' && tag.toLowerCase() === 'input' && !['button', 'submit', 'reset', 'checkbox', 'radio', 'file'].includes((type ?? 'text').toLowerCase());
}

/** A file's size for the read-only file line: bytes, KB or MB. */
export function fileSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
