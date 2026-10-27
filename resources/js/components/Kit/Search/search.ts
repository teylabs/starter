/*
 * Global search's client logic, plain data with no Vue: the results the server sent, flattened for arrow keys, and the keyboard rule that opens the
 * palette. The server decides every result; nothing here filters or ranks.
 */
export type SearchResult = { id: string; label: string; url: string | null; description: string | null; meta: string | null };
export type SearchGroup = { key: string; label: string; results: SearchResult[] };
export type SearchOption = SearchResult & { group: string; optionId: string };

/** Every result in display order, each with a DOM id unique across groups (a provider's ids are only unique within it). */
export function flatten(groups: SearchGroup[]): SearchOption[] {
    return groups.flatMap((group) => group.results.map((result) => ({ ...result, group: group.key, optionId: `search-${group.key}-${result.id}`.replace(/[^A-Za-z0-9_-]/g, '-') })));
}

/** The next active index for ArrowDown (+1) or ArrowUp (-1), wrapping; -1 (nothing active) when there are no options. */
export function move(active: number, count: number, step: 1 | -1): number {
    if (count === 0) return -1;
    if (active < 0) return step === 1 ? 0 : count - 1;
    return (active + step + count) % count;
}

/**
 * Whether a key press opens search: ⌘K or Ctrl+K anywhere, or `/` when the person is not typing. Never while composing text, never with Alt, and never
 * when another dialog or menu is open.
 */
export function opensSearch(event: { key: string; metaKey: boolean; ctrlKey: boolean; altKey: boolean; isComposing: boolean; defaultPrevented: boolean }, typing: boolean, overlayOpen: boolean): boolean {
    if (event.defaultPrevented || event.isComposing || event.altKey || overlayOpen) return false;
    if (event.key.toLowerCase() === 'k' && (event.metaKey || event.ctrlKey)) return true;
    return event.key === '/' && !typing && !event.metaKey && !event.ctrlKey;
}
