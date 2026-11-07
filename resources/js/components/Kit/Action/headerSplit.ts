// How a page header's actions split: one filled primary, up to two outline secondaries, and everything else behind More. Pure: Page, FloatingToolbar and the
// Workbench's actions row all ask this, so the same descriptors split the same way wherever they render. Nothing here checks a permission.

export type ActionPriority = 'primary' | 'secondary' | 'overflow';
export type SplittableAction = {
    destructive?: boolean;
    priority?: ActionPriority | null;
};
export type HeaderSplit<T> = {
    primary: T | null;
    secondary: T[];
    overflow: T[];
};

/** The secondaries shown beside the primary; any more go behind More. */
export const HEADER_SECONDARY_LIMIT = 2;
/** The most actions a header keeps on today's ActionBar path when none declares a priority. */
export const HEADER_DEFAULT_LIMIT = 3;

/**
 * Whether a header keeps today's ActionBar path: no action declares a priority and there are three or fewer, so its output is unchanged by this split (the
 * ActionBar placement rule and its container fit still decide).
 */
export function keepsActionBar(actions: SplittableAction[]): boolean {
    return (
        actions.length <= HEADER_DEFAULT_LIMIT &&
        !actions.some((action) => action.priority)
    );
}

/**
 * The split, descriptor order kept in every list. A declared priority always wins: the first `primary` is the primary (a later one becomes a secondary, warned
 * about in development), `overflow` goes behind More. Undeclared actions fill what is left: the first non-destructive one is the primary when none is declared,
 * the next non-destructive ones fill the secondaries after the declared ones; a destructive action never defaults to primary or secondary. Secondaries beyond
 * HEADER_SECONDARY_LIMIT go behind More. Navigation GETs count like any other action. With `phone` only the primary stays visible: the secondaries move behind
 * More, ahead of what was already there.
 */
export function headerSplit<T extends SplittableAction>(
    actions: T[],
    {
        phone = false,
        warn,
    }: { phone?: boolean; warn?: (message: string) => void } = {},
): HeaderSplit<T> {
    const declared = actions.filter((action) => action.priority === 'primary');
    if (declared.length > 1) {
        const message = `Page header actions: ${declared.length} actions declare priority 'primary'; the first stays primary, the rest show as secondary.`;
        if (warn) warn(message);
        else if ((import.meta as { env?: { DEV?: boolean } }).env?.DEV)
            console.warn(message);
    }
    const primary =
        declared[0] ??
        actions.find((action) => !action.priority && !action.destructive) ??
        null;
    const rest = actions.filter((action) => action !== primary);
    const explicit = rest.filter(
        (action) =>
            action.priority === 'primary' || action.priority === 'secondary',
    );
    const room = Math.max(0, HEADER_SECONDARY_LIMIT - explicit.length);
    const filling = rest
        .filter((action) => !action.priority && !action.destructive)
        .slice(0, room);
    const shown = new Set<T>([
        ...explicit.slice(0, HEADER_SECONDARY_LIMIT),
        ...filling,
    ]);
    const secondary = rest.filter((action) => shown.has(action));
    const overflow = rest.filter((action) => !shown.has(action));
    return phone
        ? { primary, secondary: [], overflow: [...secondary, ...overflow] }
        : { primary, secondary, overflow };
}

/** A page header's status sugar: a small icon and a label in the header's live region. */
export type HeaderStatus = {
    state: 'saved' | 'saving' | 'idle' | 'error';
    label: string;
};
