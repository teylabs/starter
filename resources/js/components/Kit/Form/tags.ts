// The rules behind InputTags, with no Vue in it (tests/js/tags.test.mjs loads it directly): splitting typed or pasted text into tags, tidying each one, and adding
// them to the current list with the limits applied. A tag is refused, never trimmed to fit: the person sees why and can fix it.

export interface TagRules {
    separators?: string[];
    max?: number | null;
    maxLength?: number | null;
    pattern?: string | null;
    normalize?: ((tag: string) => string) | null;
    locale?: string;
}
export type TagRejection = {
    tag: string;
    reason: 'duplicate' | 'too_long' | 'invalid' | 'too_many';
};

const escape = (text: string) => text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

// Text split on the separators and on line breaks (a pasted column), each piece tidied; empty pieces drop out.
export function splitTags(text: string, rules: TagRules = {}): string[] {
    const separators = rules.separators ?? [',', ';'];
    const pattern = new RegExp(
        ['\\r?\\n', '\\t', ...separators.map(escape)].join('|'),
    );
    return text
        .split(pattern)
        .map((piece) => tidyTag(piece, rules))
        .filter((piece) => piece !== '');
}

// Surrounding space trimmed and inner runs of space collapsed, then the caller's `normalize` (lowercasing, a leading # removed, …).
export function tidyTag(tag: string, rules: TagRules = {}): string {
    const tidy = tag.replace(/\s+/g, ' ').trim();
    return rules.normalize ? rules.normalize(tidy).trim() : tidy;
}

// The case-insensitive identity of a tag in the locale ("Montréal" and "MONTRÉAL" are one tag; Turkish dotted I folds the Turkish way under tr).
export const tagKey = (tag: string, locale = 'en-CA') =>
    tag.toLocaleLowerCase(locale);

export function addTags(
    current: string[],
    incoming: string[],
    rules: TagRules = {},
): { values: string[]; added: string[]; rejected: TagRejection[] } {
    const values = [...current];
    const keys = new Set(values.map((tag) => tagKey(tag, rules.locale)));
    const added: string[] = [];
    const rejected: TagRejection[] = [];
    const pattern = rules.pattern
        ? new RegExp(`^(?:${rules.pattern})$`, 'u')
        : null;
    for (const raw of incoming) {
        const tag = tidyTag(raw, rules);
        if (tag === '') continue;
        const key = tagKey(tag, rules.locale);
        if (keys.has(key)) rejected.push({ tag, reason: 'duplicate' });
        else if (rules.maxLength && [...tag].length > rules.maxLength)
            rejected.push({ tag, reason: 'too_long' });
        else if (pattern && !pattern.test(tag))
            rejected.push({ tag, reason: 'invalid' });
        else if (rules.max && values.length >= rules.max)
            rejected.push({ tag, reason: 'too_many' });
        else {
            values.push(tag);
            keys.add(key);
            added.push(tag);
        }
    }
    return { values, added, rejected };
}

export interface TagsMessages {
    duplicate: string;
    too_long: string;
    invalid: string;
    too_many: string;
}
const fallback: TagsMessages = {
    duplicate: '“:tag” is already added.',
    too_long: 'Tags are at most :count characters.',
    invalid: '“:tag” is not a valid tag.',
    too_many: 'At most :count tags.',
};
// The strings from the kit's `ui` lang file (`tags.*`, shared as `kitUi`), then the caller's `messages`.
export function tagsMessages(
    shared: unknown,
    overrides: Partial<TagsMessages> = {},
): TagsMessages {
    const from =
        shared && typeof shared === 'object'
            ? (shared as Record<string, unknown>)
            : {};
    const out = { ...fallback };
    for (const key of Object.keys(fallback) as (keyof TagsMessages)[]) {
        const value = overrides[key] ?? from[key];
        if (typeof value === 'string' && value !== '') out[key] = value;
    }
    return out;
}

// The message for the first refusal, with :tag and :count filled in.
export function rejectionMessage(
    rejection: TagRejection | undefined,
    messages: TagsMessages,
    rules: TagRules = {},
): string {
    if (!rejection) return '';
    const count = rejection.reason === 'too_long' ? rules.maxLength : rules.max;
    return messages[rejection.reason]
        .replace(':tag', rejection.tag)
        .replace(':count', String(count ?? ''));
}
