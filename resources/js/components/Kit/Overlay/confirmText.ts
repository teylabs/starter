// Typed confirmation, without Vue: whether what was typed matches the text a destructive action asks for, and the field the visit sends so the server re-checks it
// (TypedConfirmation on the server; the client gate alone is never trusted). Surrounding whitespace is ignored (a pasted name often carries some); case matters
// unless `caseSensitive` is false.
export function typedMatches(
    typed: string,
    expected: string,
    caseSensitive = true,
): boolean {
    if (expected === '') return false;
    const given = typed.trim();
    return caseSensitive
        ? given === expected
        : given.toLocaleLowerCase() === expected.toLocaleLowerCase();
}

/** The data a confirmed visit sends: `{ confirmation }` when the action asked for typed text, nothing otherwise. */
export function confirmationData(
    typed: string | undefined,
): { confirmation: string } | undefined {
    return typed === undefined ? undefined : { confirmation: typed.trim() };
}
