/*
 * The kit's activity stream contract: what a host's presenter sends (map into `Tey\Kit\Features\Core\Data\ActivityEntryData` on the server, which serializes to this
 * shape) and the pure helpers ActivityStream and useCursorFeed run on. Source-agnostic: an audit log, a feed service and a record's own timeline all fit.
 */

/** A kit tone, as Badge and the toasts use them. */
export type ActivityTone =
    'success' | 'warning' | 'danger' | 'info' | 'neutral';

/**
 * The entry's one line: plain `text` (rendered as text, always safe), or `html` the host's server has already escaped. The kit never escapes or sanitizes `html`:
 * it reaches the DOM only through `trustedHtml()`, so send it only when every value interpolated into it was escaped on the server.
 */
export type ActivityHeadline =
    { text: string; html?: never } | { html: string; text?: never };

export type ActivityMeta = { label: string; value: string };

export type ActivityDay = { date: string; label: string };

export type ActivityEntry = {
    id: string;
    kind: 'activity' | 'group';
    /** Null renders the row with its time and meta only. */
    headline: ActivityHeadline | null;
    /** ISO 8601, for `<time datetime>`; null when the entry has no moment of its own. */
    occurredAt: string | null;
    /** The display strings, formatted on the server. Without `timestamp`, the stream formats `occurredAt` in the viewer's locale and zone. */
    timestamp: string | null;
    timestampTitle: string | null;
    /** When the change takes effect, if that differs from when it was recorded: ISO date, and its display label. The row reads "effective <label> · recorded <timestamp>". */
    effectiveOn?: string | null;
    effectiveOnLabel?: string | null;
    /** A kit tone. Hosts may send their own vocabulary: anything else is shown as neutral (activityTone), with a warning in development. */
    intent: ActivityTone | string | null;
    meta: ActivityMeta[];
    /** A group's full size, which may exceed `children` (then `truncated` is true). Null on a single activity. */
    count: number | null;
    truncated: boolean;
    children: ActivityEntry[];
    /** The day the entry belongs to, when the stream is cut into days. */
    day: ActivityDay | null;
};

export type ActivitySection = {
    key: string;
    day: ActivityDay | null;
    entries: ActivityEntry[];
};

/** Consecutive entries of one day form a section; an entry with no day joins the section before it. With `days` off, one section holds every entry. */
export function activitySections(
    entries: ActivityEntry[],
    days: boolean,
): ActivitySection[] {
    const sections: ActivitySection[] = [];
    for (const entry of entries) {
        const day = days ? entry.day : null;
        const last = sections.at(-1);
        if (last && (day === null || last.key === day.date)) {
            last.entries.push(entry);
        } else {
            sections.push({ key: day?.date ?? '', day, entries: [entry] });
        }
    }
    return sections;
}

/** The one path from a headline to v-html: the host's server-escaped `html`, else null (the row renders `text` as text). */
export function trustedHtml(headline: ActivityHeadline | null): string | null {
    return headline && 'html' in headline && typeof headline.html === 'string'
        ? headline.html
        : null;
}

/** The headline's plain text, for a headline sent as `text`; empty for none. */
export function headlineText(headline: ActivityHeadline | null): string {
    return headline && 'text' in headline && typeof headline.text === 'string'
        ? headline.text
        : '';
}

export const ACTIVITY_TONES: readonly ActivityTone[] = [
    'success',
    'warning',
    'danger',
    'info',
    'neutral',
];

/** The kit tone an intent shows as: a kit tone as itself, anything else (a host's own vocabulary, null) as neutral. `warn` hears about an unknown one. */
export function activityTone(
    intent: string | null | undefined,
    warn?: (intent: string) => void,
): ActivityTone {
    if (intent == null) return 'neutral';
    if ((ACTIVITY_TONES as readonly string[]).includes(intent))
        return intent as ActivityTone;
    warn?.(intent);
    return 'neutral';
}

/** One page of a cursor-paged feed, as the server sends it under the page prop: the entries, and the cursor and sync token under the keys the host names. */
export type CursorFeedPage<T> = { entries: T[] } & Record<string, unknown>;

export type CursorFeedOptions<T> = {
    first: CursorFeedPage<T>;
    cursorKey: string;
    syncKey: string;
    /** Asks the server for the page after `cursor` (null: the head page). The page arrives through `receive`. */
    fetch: (cursor: string | null) => void;
    /** Wraps the state when it is made (Vue's `reactive`), so the controller's own writes are tracked. */
    wrap?: <S extends object>(state: S) => S;
};

/**
 * The paging rules, without Vue or Inertia: pages accumulate (an entry already held is not added twice); only a null cursor ends the feed, so an empty page that
 * carries a cursor keeps following it; a changed sync token means the history was rewritten, so the held entries are dropped and the head page is read again.
 */
export function createCursorFeed<T extends { id: string }>(
    options: CursorFeedOptions<T>,
) {
    const read = (page: CursorFeedPage<T>, key: string) =>
        typeof page[key] === 'string' ? (page[key] as string) : null;
    const state = (options.wrap ?? (<S extends object>(value: S) => value))({
        entries: [...options.first.entries],
        cursor: read(options.first, options.cursorKey),
        sync: read(options.first, options.syncKey),
        loading: false,
        resynced: false,
    });
    let appending = false;
    let resyncing = false;

    function loadMore(): boolean {
        if (!state.cursor || state.loading) return false;
        appending = true;
        state.loading = true;
        options.fetch(state.cursor);
        return true;
    }

    /** A page arrived (the prop changed). Returns how many entries were added to the end. */
    function receive(page: CursorFeedPage<T>): number {
        const sync = read(page, options.syncKey);
        const cursor = read(page, options.cursorKey);
        state.loading = false;
        if (appending && sync === state.sync) {
            appending = false;
            const held = new Set(state.entries.map((entry) => entry.id));
            const added = page.entries.filter((entry) => !held.has(entry.id));
            state.entries = [...state.entries, ...added];
            state.cursor = cursor;
            if (!page.entries.length && cursor) loadMore();
            return added.length;
        }
        if (appending) {
            appending = false;
            resyncing = true;
            state.resynced = true;
            state.loading = true;
            options.fetch(null);
            return 0;
        }
        // The head page: the first one, a resync's, or a reload the host made itself.
        state.resynced = resyncing || state.resynced;
        resyncing = false;
        state.entries = [...page.entries];
        state.cursor = cursor;
        state.sync = sync;
        return 0;
    }

    /** A request that failed: nothing arrives, so the feed stops loading and keeps what it holds. */
    function failed(): void {
        appending = false;
        resyncing = false;
        state.loading = false;
    }

    return { state, loadMore, receive, failed };
}
