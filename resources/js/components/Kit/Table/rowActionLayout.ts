// How RowActions lays out one row's actions, and what each is called. Pure, so the rules are checked without a browser.
//
// `overflow` (quiet inline actions plus a "…" menu): one or two plain actions show inline; three or more become the first inline plus a menu; a destructive action
// is a menu item whenever another action exists (a lone one is a button). `split` (the split button): the first plain action is the primary, joined in a ButtonGroup to
// a chevron menu holding every other action; a row with no plain action keeps the overflow layout. With no variant, a row whose overflow layout would be exactly one
// inline action plus a menu is `split`, anything else `overflow`. An explicit variant always wins.
//
// Labels are verbs ("Remove", not "Remove Ana Lopez's access to Billing"): the record belongs in the accessible name and in the confirmation. A visible label over
// 24 characters or 3 words is clamped with an ellipsis (its full text is the control's `title`), and in development each such label warns once.
export type RowActionVariant = 'split' | 'overflow';

export type LaidOut<A> = {
    variant: RowActionVariant;
    /** split: the primary action, joined to the menu. */
    primary: A | null;
    /** overflow: the quiet inline actions. */
    inline: A[];
    /** Actions shown as lone buttons (a row of destructive actions only). */
    lone: A[];
    /** The menu: behind the chevron (split) or the "…" trigger (overflow). */
    menu: A[];
};

export const LABEL_MAX_CHARACTERS = 24;
export const LABEL_MAX_WORDS = 3;

export function layOut<A extends { destructive: boolean }>(
    actions: A[],
    variant?: RowActionVariant | null,
): LaidOut<A> {
    const plain = actions.filter((action) => !action.destructive);
    const destructive = actions.filter((action) => action.destructive);
    const inline = plain.length >= 3 ? plain.slice(0, 1) : plain;
    const menu = [
        ...(plain.length >= 3 ? plain.slice(1) : []),
        ...(plain.length ? destructive : []),
    ];
    const lone = plain.length ? [] : destructive;
    const chosen: RowActionVariant =
        variant ??
        (inline.length === 1 && menu.length >= 1 ? 'split' : 'overflow');
    if (chosen === 'split' && plain.length) {
        return {
            variant: 'split',
            primary: plain[0],
            inline: [],
            lone: [],
            menu: [...plain.slice(1), ...destructive],
        };
    }

    return { variant: chosen, primary: null, inline, lone, menu };
}

const words = (label: string) => label.trim().split(/\s+/).filter(Boolean);

/** True when a visible label is too long for a row: over 24 characters or 3 words. */
export function tooLong(label: string): boolean {
    return (
        label.trim().length > LABEL_MAX_CHARACTERS ||
        words(label).length > LABEL_MAX_WORDS
    );
}

/** The visible label: the first three words, then at most 24 characters, ending in an ellipsis when anything was cut. */
export function clampLabel(label: string): string {
    if (!tooLong(label)) return label;
    let text = words(label).slice(0, LABEL_MAX_WORDS).join(' ');
    if (text.length > LABEL_MAX_CHARACTERS - 1)
        text = text.slice(0, LABEL_MAX_CHARACTERS - 1).trimEnd();

    return `${text}…`;
}

/** The accessible name: the action's own `ariaLabel`, else "<Label> <record>", else the label. */
export function actionName(
    action: { label: string; ariaLabel?: string | null },
    record: string | null | undefined,
): string {
    if (action.ariaLabel) return action.ariaLabel;

    return record ? `${action.label} ${record}` : action.label;
}

/** The confirmation's title, short and bold: the server's ("Remove permission?"), else "<Label> this <noun>?" ("Remove this member?"), else "<Label> this item?". The record never goes in the title. */
export function confirmTitle(
    action: {
        label: string;
        confirm?: { title?: string | null } | null;
    },
    noun?: string | null,
): string {
    const title = action.confirm?.title?.trim();
    if (title) return title;

    return `${action.label} this ${noun?.trim() || 'item'}?`;
}

/** The record named in the confirmation's body (where a long name wraps): only when the server gave no title, whose description then names it itself. */
export function confirmRecord(
    action: { confirm?: { title?: string | null } | null },
    record: string | null | undefined,
): string | null {
    if (action.confirm?.title?.trim()) return null;

    return record?.trim() || null;
}

const warned = new Set<string>();

/** In development, warns once per label that is clamped. `warn` replaces console.warn (tests). */
export function warnLongLabel(
    label: string,
    warn?: (message: string) => void,
): void {
    if (!tooLong(label) || warned.has(label)) return;
    warned.add(label);
    const message = `RowActions: "${label}" is clamped; row action labels are verbs; put the record in the confirm.`;
    if (warn) warn(message);
    else if ((import.meta as { env?: { DEV?: boolean } }).env?.DEV)
        console.warn(message);
}
