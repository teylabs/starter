// InputSelect's pure logic (InputSelect.md): option shapes, filtering, the search threshold, ordering, the action rows, highlight moves and copy.
// No Vue here, so node tests it directly.

export type InputSelectValue = string | number;
export interface InputSelectOption { value: InputSelectValue; label: string; description?: string; disabled?: boolean }
export interface InputSelectGroup { label: string; options: InputSelectOption[] }
export type InputSelectOptions = InputSelectOption[] | InputSelectGroup[];

/** The default strings, by key under `select.` in the kit's `ui` lang file. `:count`, `:label` and `:query` are replaced. */
export const inputSelectMessageKeys = ['empty', 'empty_query', 'too_short', 'loading', 'count', 'chips', 'remove', 'clear', 'clear_all', 'select_all', 'deselect_all', 'create', 'none', 'live.results', 'live.result', 'live.no_results', 'live.added', 'live.removed', 'live.cleared', 'live.created'] as const;
export type InputSelectMessageKey = (typeof inputSelectMessageKeys)[number];
export type InputSelectMessages = Record<InputSelectMessageKey, string>;

/** Search turns on automatically past this many options. */
export const SEARCH_THRESHOLD = 8;

export const isGrouped = (options: InputSelectOptions): options is InputSelectGroup[] => options.length > 0 && 'options' in options[0];

/** Every group, a flat list being one unnamed group. */
export const groupsOf = (options: InputSelectOptions): { label: string | null; options: InputSelectOption[] }[] => (isGrouped(options) ? options : [{ label: null, options: options as InputSelectOption[] }]);

export const flatOptions = (options: InputSelectOptions): InputSelectOption[] => groupsOf(options).flatMap(group => group.options);

/** Case- and accent-insensitive text for matching. */
export const fold = (text: string): string => text.normalize('NFD').replace(/\p{M}/gu, '').toLocaleLowerCase();

/** Search on or off: `null` is automatic (past 8 options, groups counted through), always on for a remote filter. */
export function searchEnabled(searchable: boolean | null | undefined, options: InputSelectOptions, filter: 'local' | 'remote'): boolean {
    if (searchable === true || searchable === false) return searchable;
    return filter === 'remote' || flatOptions(options).length > SEARCH_THRESHOLD;
}

/** The groups left after the typed text: labels containing it, empty groups hidden. A remote filter leaves filtering to the caller. */
export function filterGroups(options: InputSelectOptions, query: string, filter: 'local' | 'remote'): { label: string | null; options: InputSelectOption[] }[] {
    const needle = fold(query.trim());
    const groups = groupsOf(options).map(group => ({ label: group.label, options: filter === 'remote' || needle === '' ? group.options : group.options.filter(option => fold(option.label).includes(needle)) }));
    return groups.filter(group => group.options.length > 0);
}

/** Chosen options first inside their group, from the values chosen when the list opened; the rest keep their order. */
export function chosenFirst(groups: { label: string | null; options: InputSelectOption[] }[], chosenAtOpen: InputSelectValue[]) {
    const chosen = new Set(chosenAtOpen);
    return groups.map(group => ({ label: group.label, options: [...group.options.filter(option => chosen.has(option.value)), ...group.options.filter(option => !chosen.has(option.value))] }));
}

/** The model as an array of values. */
export const valuesOf = (model: InputSelectValue | InputSelectValue[] | null | undefined): InputSelectValue[] => (Array.isArray(model) ? model : model === null || model === undefined ? [] : [model]);

/** Multiple mode: the value added at the end, or removed. */
export const toggleValue = (values: InputSelectValue[], value: InputSelectValue): InputSelectValue[] => (values.includes(value) ? values.filter(v => v !== value) : [...values, value]);

/** The select-all row: every enabled shown option chosen means it removes them, otherwise it adds the missing ones in list order. */
export function selectAll(values: InputSelectValue[], shown: InputSelectOption[]): { values: InputSelectValue[]; all: boolean } {
    const enabled = shown.filter(option => !option.disabled).map(option => option.value);
    const all = enabled.length > 0 && enabled.every(value => values.includes(value));
    return { all, values: all ? values.filter(value => !enabled.includes(value)) : [...values, ...enabled.filter(value => !values.includes(value))] };
}

/** True when every enabled shown option is chosen (the row reads "Deselect all"). */
export const allChosen = (values: InputSelectValue[], shown: InputSelectOption[]): boolean => selectAll(values, shown).all;

/** The create row shows for typed text that matches no option label exactly (case-insensitive, trimmed). */
export function showCreate(query: string, options: InputSelectOptions): boolean {
    const text = query.trim();
    return text !== '' && !flatOptions(options).some(option => option.label.trim().toLocaleLowerCase() === text.toLocaleLowerCase());
}

/** Remote filter: emit `search` only once the trimmed text is long enough (`0` emits for any text). */
export const searchReady = (query: string, minLength: number): boolean => query.trim().length >= minLength;

/**
 * The next highlight among the rows (`null` entries are not highlightable: disabled options). `by` steps, stopping at the ends (no wrap);
 * `'first'` and `'last'` jump. From nothing highlighted a forward move lands on the first row, a backward one on the last.
 */
export function moveHighlight<T>(rows: (T | null)[], current: number, by: number | 'first' | 'last'): number {
    const enabled = rows.map((row, index) => (row === null ? -1 : index)).filter(index => index >= 0);
    if (enabled.length === 0) return -1;
    if (by === 'first') return enabled[0];
    if (by === 'last') return enabled[enabled.length - 1];
    const position = enabled.indexOf(current);
    if (position === -1) return by > 0 ? enabled[0] : enabled[enabled.length - 1];
    return enabled[Math.max(0, Math.min(enabled.length - 1, position + by))];
}

/**
 * Chips by keyboard in an empty field. `chip` is the highlighted chip's index or null. Returns the new highlight and, when a chip goes, the index removed.
 * Backspace highlights the last chip, then removes it; ArrowLeft moves back, ArrowRight forward and out into the text; Delete or Backspace removes the highlighted one and returns to the text.
 */
export function chipKey(key: string, chip: number | null, count: number): { chip: number | null; remove: number | null; handled: boolean } {
    if (count === 0) return { chip: null, remove: null, handled: false };
    if (key === 'Backspace' && chip === null) return { chip: count - 1, remove: null, handled: true };
    if ((key === 'Backspace' || key === 'Delete') && chip !== null) return { chip: null, remove: chip, handled: true };
    if (key === 'ArrowLeft') return { chip: chip === null ? count - 1 : Math.max(0, chip - 1), remove: null, handled: true };
    if (key === 'ArrowRight' && chip !== null) return { chip: chip + 1 < count ? chip + 1 : null, remove: null, handled: true };
    return { chip, remove: null, handled: false };
}

/** A string with its `:count`, `:label` and `:query` replaced. */
export const interpolate = (text: string, replace: Record<string, string | number> = {}): string => text.replace(/:(count|label|query)\b/g, (match, key: string) => (key in replace ? String(replace[key]) : match));

/** The results announcement after filtering settles. */
export const resultsMessage = (messages: InputSelectMessages, count: number): string => (count === 0 ? messages['live.no_results'] : interpolate(count === 1 ? messages['live.result'] : messages['live.results'], { count }));

/**
 * The strings for one use: the shared translations (the `select` part of the kit's `ui` lang file, nested), then `messages` overrides.
 * A string missing from both is its key, so a gap shows instead of hiding behind built-in English.
 */
export function selectMessages(shared: unknown, overrides: Partial<InputSelectMessages> = {}): InputSelectMessages {
    const lookup = (key: string): unknown => key.split('.').reduce<unknown>((node, part) => (node && typeof node === 'object' ? (node as Record<string, unknown>)[part] : undefined), shared);
    const messages = {} as InputSelectMessages;
    for (const key of inputSelectMessageKeys) {
        const found = overrides[key] ?? lookup(key);
        messages[key] = typeof found === 'string' ? found : `select.${key}`;
    }
    return messages;
}
