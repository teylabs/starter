/*
 * The headless half of the kit Checklist: a server-computed list of steps (ChecklistItem on the server) and what follows from it. The server decides each
 * state; nothing here changes one. Any component, the kit's own or a host's, draws a checklist from checklistProgress() (useChecklist() for the reactive form).
 */
import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';

export const CHECKLIST_STATES = [
    'pending',
    'active',
    'done',
    'failed',
    'skipped',
] as const;
export type ChecklistState = (typeof CHECKLIST_STATES)[number];
export type ChecklistAction = {
    label: string;
    url?: string | null;
    name?: string | null;
};
/** One reason a step does not hold, with the field it concerns and a link to the fix when there is one (GateError on the server). */
export type ChecklistError = {
    message: string;
    field?: string | null;
    url?: string | null;
};
export type ChecklistItem = {
    key: string;
    label: string;
    state: ChecklistState;
    detail?: string | null;
    reason?: string | null;
    required?: boolean;
    action?: ChecklistAction | null;
    /** The reasons the step does not hold yet (a finalize gate's step). Drawn under the label while the step is not done. */
    errors?: readonly ChecklistError[] | null;
    /** Non-blocking notes (an optional photo missing): shown in the step, never holding it back (a finalize gate's step warnings). */
    warnings?: readonly ChecklistError[] | null;
    /** The step's fixes as server action descriptors (Confirm, a link), already gated. Drawn as small buttons. */
    actions?: readonly ActionDescriptor[] | null;
};
/** Progress as the server computed it (a finalize gate's GateProgress). Given to Checklist, it decides `complete`; without it the list computes its own. */
export type ServerChecklistProgress = {
    complete: boolean;
    currentKey?: string | null;
    revision?: string;
};

export type ChecklistProgress = {
    total: number;
    /** Done, plus skipped: a skipped step is settled. */
    settled: number;
    done: number;
    failed: number;
    /** Every required step is done or skipped, and nothing has failed. An empty list is not complete. */
    complete: boolean;
    /** The active step, else the first failed one, else null. */
    current: ChecklistItem | null;
    /** 0–100, from settled over total. */
    percent: number;
};

/** What a list of items adds up to. An unknown state counts as pending, so a newer server never reads as done. */
export function checklistProgress(
    items: readonly ChecklistItem[] | null | undefined,
): ChecklistProgress {
    const list = items ?? [];
    const state = (item: ChecklistItem): ChecklistState =>
        CHECKLIST_STATES.includes(item.state) ? item.state : 'pending';
    const done = list.filter((item) => state(item) === 'done').length;
    const settled =
        done + list.filter((item) => state(item) === 'skipped').length;
    const failed = list.filter((item) => state(item) === 'failed').length;
    const required = list.filter((item) => item.required !== false);
    return {
        total: list.length,
        settled,
        done,
        failed,
        complete:
            list.length > 0 &&
            failed === 0 &&
            required.every(
                (item) => state(item) === 'done' || state(item) === 'skipped',
            ),
        current:
            list.find((item) => state(item) === 'active') ??
            list.find((item) => state(item) === 'failed') ??
            null,
        percent: list.length ? Math.round((settled / list.length) * 100) : 0,
    };
}

/** The sentence a screen reader hears when the list changes: the current step, else the outcome. */
export function checklistMessage(
    progress: ChecklistProgress,
    labels: Partial<Record<'complete' | 'failed' | 'waiting', string>> = {},
): string {
    if (progress.current?.state === 'active') return progress.current.label;
    if (progress.failed)
        return labels.failed ?? `${progress.current?.label ?? 'A step'} failed`;
    if (progress.complete && progress.total)
        return labels.complete ?? 'All steps done';
    return labels.waiting ?? `${progress.settled} of ${progress.total} done`;
}
