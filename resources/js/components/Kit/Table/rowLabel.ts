// What names a DataTable row to a screen reader: `rowLabel` (a column key, or a function of the row) gives "Actions for Ana Lopez" instead of "Actions for row 3".
// An empty or missing value falls back to the row's number on the page, so every control is still named.
export type RowLabel<R> =
    string | ((row: R) => string | null | undefined) | null | undefined;

export function rowName<R extends Record<string, unknown>>(
    row: R,
    index: number,
    rowLabel: RowLabel<R>,
): string {
    const value =
        typeof rowLabel === 'function'
            ? rowLabel(row)
            : typeof rowLabel === 'string'
              ? row[rowLabel]
              : null;
    const text =
        value === null || value === undefined ? '' : String(value).trim();
    return text === '' ? `row ${index + 1}` : text;
}
