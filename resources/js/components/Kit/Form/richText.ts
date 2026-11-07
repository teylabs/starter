import type { JSONContent } from '@tiptap/core';

/*
 * InputRichText's document: a strict, portable subset of ProseMirror JSON that a host stores and its server can validate the same way. Blocks are paragraphs,
 * headings (levels 2 and 3 only), blockquotes and lists (a list item starts with a paragraph); inline content is text with bold, italic, strike, inline code and
 * link marks, and hard breaks. Code blocks, rules, underline and tables are not part of it. An image is a block only when the host opts in (`allow` naming `image`
 * and an `imageSources` pattern its server enforces too); otherwise it is the host's own block beside the field (file-intent).
 */

export type RichMark =
    | { type: 'bold' | 'italic' | 'strike' | 'inlineCode' }
    | { type: 'link'; attrs: { href: string; title?: string | null } }
    | RichTextStyleMark;
/** Only with `allow` naming `textStyle` (mirrored content, such as slides imported from a presentation tool). Each value is checked; none passes through as raw CSS. */
export type RichTextStyleMark = {
    type: 'textStyle';
    attrs: { color?: string; fontFamily?: string; fontSize?: string | number };
};
/** Only with `allow` naming `textAlign`, on a paragraph or a heading. */
export type RichTextAlign = 'left' | 'center' | 'right' | 'justify';
export type RichInline =
    { type: 'text'; text: string; marks?: RichMark[] } | { type: 'hardBreak' };
export type RichParagraph = {
    type: 'paragraph';
    attrs?: { textAlign: RichTextAlign };
    content?: RichInline[];
};
export type RichBlock =
    | RichParagraph
    | {
          type: 'heading';
          attrs: { level: 2 | 3; textAlign?: RichTextAlign };
          content?: RichInline[];
      }
    | { type: 'blockquote'; content: RichBlock[] }
    | { type: 'bulletList'; content: RichListItem[] }
    | {
          type: 'orderedList';
          attrs?: { start?: number };
          content: RichListItem[];
      }
    | RichImage;
/** Only with `allow` naming `image`: a block whose `src` matches the host's `imageSources` (its own reference, such as `media:{uuid}`, never a pasted URL). */
export type RichImage = {
    type: 'image';
    attrs: { src: string; alt?: string | null };
};
export type RichListItem = {
    type: 'listItem';
    content: [RichParagraph, ...RichBlock[]];
};
export type RichDocument = { type: 'doc'; content: RichBlock[] };

/** Files pasted or dropped on the field. The host uploads them and inserts its own image blocks, so no URL ever enters the document. */
export type FileIntent = {
    source: 'paste' | 'drop';
    files: File[];
    /** The ProseMirror position at the event, not a durable block id. */ position: number;
};
export type ContentError = { code: 'invalid-document'; message: string };

/** The toolbar's controls, in the order the default toolbar shows them. `image` shows only with images allowed and an `uploadImage` callback. */
export const RICH_TEXT_TOOLS = [
    'heading2',
    'heading3',
    'bold',
    'italic',
    'strike',
    'code',
    'link',
    'bulletList',
    'orderedList',
    'blockquote',
    'image',
    'undo',
    'redo',
] as const;
export type RichTextTool = (typeof RICH_TEXT_TOOLS)[number];

export function emptyDocument(): RichDocument {
    return { type: 'doc', content: [{ type: 'paragraph' }] };
}

/**
 * Whether a link may live in a document: http(s) with a host and no credentials, mailto and tel, and relative links. Anything with another scheme is refused,
 * as are protocol-relative links, whitespace, backslashes and control characters (raw or percent-encoded). `privateLinks` is the host's own internal-URI pattern
 * (`/^app:\/\/record\/[0-9]+$/`): a link matching it is accepted only when the field is given the pattern, and the host's server must enforce the same rule.
 */
export function isSafeLink(
    value: string,
    privateLinks?: RegExp | null,
): boolean {
    if (
        !value ||
        value !== value.trim() ||
        /[\s\\\u007f-\u009f]/u.test(value) ||
        /%(?:0[0-9a-f]|1[0-9a-f]|7f|5c)/i.test(value)
    )
        return false;
    if (Array.from(value).some((character) => character.charCodeAt(0) < 32))
        return false;
    if (value.startsWith('//')) return false;
    // A fresh, stateless copy: a /g or /y pattern's own `test` moves its lastIndex, so the same link would alternate between accepted and refused.
    if (
        privateLinks &&
        new RegExp(
            privateLinks.source,
            privateLinks.flags.replace(/[gy]/g, ''),
        ).test(value)
    )
        return true;
    if (/^https?:\/\//i.test(value)) {
        try {
            const url = new URL(value);
            return (
                Boolean(url.hostname) &&
                !url.username &&
                !url.password &&
                !/^https?:\/\/[^/?#]*@/i.test(value)
            );
        } catch {
            return false;
        }
    }
    if (/^(?:mailto|tel):[^/?#]+(?:[?#].*)?$/i.test(value)) return true;
    return !/^[^/?#]*:/.test(value);
}

const BLOCKS = [
    'paragraph',
    'heading',
    'blockquote',
    'bulletList',
    'orderedList',
];
const MARKS = ['bold', 'italic', 'strike', 'inlineCode'];

/** What a field may preserve beyond the strict subset, opted into per field (`allow`). Off by default: the subset is unchanged. */
export const RICH_TEXT_ALLOW = ['textAlign', 'textStyle', 'image'] as const;
export type RichTextAllow = (typeof RICH_TEXT_ALLOW)[number];
const ALIGNMENTS: readonly string[] = ['left', 'center', 'right', 'justify'];

/** A colour as #rgb, #rrggbb, rgb(r, g, b) or rgba(r, g, b, a): channels 0–255, alpha 0–1. Nothing else (no names, no functions, no CSS). */
export function isSafeColor(value: unknown): value is string {
    if (typeof value !== 'string') return false;
    if (/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i.test(value)) return true;
    const match =
        /^rgb(a?)\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(?:,\s*(0|1|0?\.\d{1,4}|1\.0+)\s*)?\)$/i.exec(
            value,
        );
    if (!match || (match[1] === 'a') !== (match[5] !== undefined)) return false;
    return [match[2], match[3], match[4]].every(
        (channel) => Number(channel) <= 255,
    );
}

/**
 * A font size: a number with px, pt, em or rem in a sane range (px 1–200, pt 1–150, em and rem 0.1–12), or a bare number, a unitless scale the host resolves
 * itself (0 < n ≤ 20, up to three decimals; mirrored decks store one), kept a number through the round trip.
 */
export function isSafeFontSize(value: unknown): value is string | number {
    if (typeof value === 'number')
        return (
            Number.isFinite(value) &&
            value > 0 &&
            value <= 20 &&
            Number(value.toFixed(3)) === value
        ); // toFixed, not value * 1000: 2.03 * 1000 is 2029.9999999999998 in floating point
    if (typeof value !== 'string') return false;
    const match = /^(\d{1,3}(?:\.\d{1,3})?)(px|pt|em|rem)$/.exec(value);
    if (!match) return false;
    const size = Number(match[1]);
    const [min, max] = {
        px: [1, 200],
        pt: [1, 150],
        em: [0.1, 12],
        rem: [0.1, 12],
    }[match[2] as 'px' | 'pt' | 'em' | 'rem'];
    return size >= min && size <= max;
}

/**
 * A font family list: up to 10 comma-separated names of letters, digits, spaces, hyphens and underscores, each bare or in one pair of matching quotes ('Open Sans',
 * "Open Sans", as presentation tools write them). No semicolons, parentheses, url() or quotes inside a name. Given `allowed` (a host's own family tokens, such as
 * ['sans', 'display']), every name must also be one of them.
 */
export function isSafeFontFamily(
    value: unknown,
    allowed?: readonly string[] | null,
): value is string {
    if (typeof value !== 'string' || value.length > 200) return false;
    const names = value.split(',').map((name) => name.trim());
    if (names.length > 10) return false;
    // With the host's list, every name (its quotes aside) must be one of the host's own; without it, the character class decides.
    return names.every((name) => {
        const match =
            /^(?:([A-Za-z0-9][A-Za-z0-9 _-]*)|'([A-Za-z0-9][A-Za-z0-9 _-]*)'|"([A-Za-z0-9][A-Za-z0-9 _-]*)")$/.exec(
                name,
            );
        return (
            match !== null &&
            (!allowed || allowed.includes(match[1] ?? match[2] ?? match[3]))
        );
    });
}

const styleRules = (
    fontFamilies?: readonly string[] | null,
): Record<string, (value: unknown) => boolean> => ({
    color: isSafeColor,
    fontFamily: (value) => isSafeFontFamily(value, fontFamilies),
    fontSize: isSafeFontSize,
});

/**
 * Validates a stored document and returns the editor's JSON (the `inlineCode` mark is tiptap's `code`). It throws on anything outside the subset (an unknown node,
 * mark or attribute, an unsafe link, an empty container, a list item not starting with a paragraph, more than 20 levels or 10,000 nodes) rather than let the editor
 * drop it silently. Null is the empty document. `allow` admits a paragraph's or heading's `textAlign` and the `textStyle` mark, each value checked strictly;
 * `fontFamilies`, when given, limits a style's font family to the host's own names.
 */
/** Whether an image `src` may live in a document: only with images allowed, and only matching the host's `imageSources` pattern. Without one, none does. */
export function isImageSource(
    value: unknown,
    imageSources?: RegExp | null,
): value is string {
    return (
        typeof value === 'string' &&
        value.length > 0 &&
        value.length <= 2048 &&
        imageSources instanceof RegExp &&
        imageSources.test(value) &&
        !/[\s\\<>"']/.test(value)
    );
}

export function toEditorDocument(
    document: RichDocument | null,
    privateLinks?: RegExp | null,
    allow: readonly RichTextAllow[] = [],
    fontFamilies?: readonly string[] | null,
    imageSources?: RegExp | null,
): JSONContent {
    const STYLES = styleRules(fontFamilies);
    const aligns = allow.includes('textAlign');
    const styles = allow.includes('textStyle');
    const blocks = allow.includes('image') ? [...BLOCKS, 'image'] : BLOCKS;
    let count = 0;
    const visit = (
        value: unknown,
        parent: string,
        depth: number,
    ): JSONContent => {
        if (
            depth > 20 ||
            ++count > 10000 ||
            !value ||
            typeof value !== 'object' ||
            Array.isArray(value)
        )
            throw new Error('Unsupported or oversized rich-text document.');
        const node = value as Record<string, unknown>;
        const type = String(node.type);
        const allowed =
            parent === ''
                ? ['doc']
                : parent === 'paragraph' || parent === 'heading'
                  ? ['text', 'hardBreak']
                  : parent === 'bulletList' || parent === 'orderedList'
                    ? ['listItem']
                    : blocks;
        if (!allowed.includes(type))
            throw new Error(`Unsupported rich-text node: ${type}.`);
        const keys = [
            'type',
            ...(type === 'text'
                ? ['text', 'marks']
                : type === 'hardBreak' || type === 'image'
                  ? []
                  : ['content']),
            ...(type === 'heading' ||
            type === 'orderedList' ||
            type === 'image' ||
            (type === 'paragraph' && aligns)
                ? ['attrs']
                : []),
        ];
        if (Object.keys(node).some((key) => !keys.includes(key)))
            throw new Error('Unsupported rich-text attributes.');
        const result: JSONContent = { type };
        if (type === 'text') {
            if (typeof node.text !== 'string' || !node.text)
                throw new Error('Text nodes require text.');
            result.text = node.text;
            if (node.marks !== undefined) {
                if (!Array.isArray(node.marks))
                    throw new Error('Invalid rich-text marks.');
                const seen = new Set<unknown>();
                result.marks = node.marks.map((value: unknown) => {
                    if (
                        !value ||
                        typeof value !== 'object' ||
                        Array.isArray(value)
                    )
                        throw new Error('Invalid rich-text mark.');
                    const mark = value as Record<string, unknown>;
                    if (seen.has(mark.type))
                        throw new Error('Duplicate rich-text mark.');
                    seen.add(mark.type);
                    if (mark.type === 'link') {
                        const attrs = mark.attrs as
                            Record<string, unknown> | undefined;
                        if (
                            Object.keys(mark).some(
                                (key) => key !== 'type' && key !== 'attrs',
                            ) ||
                            !attrs ||
                            typeof attrs !== 'object' ||
                            Array.isArray(attrs) ||
                            Object.keys(attrs).some(
                                (key) => key !== 'href' && key !== 'title',
                            ) ||
                            (attrs.title !== undefined &&
                                attrs.title !== null &&
                                typeof attrs.title !== 'string') ||
                            typeof attrs.href !== 'string' ||
                            !isSafeLink(attrs.href, privateLinks)
                        ) {
                            throw new Error('Unsupported or unsafe link.');
                        }
                        return {
                            type: 'link',
                            attrs: {
                                href: attrs.href,
                                title:
                                    (attrs.title as
                                        string | null | undefined) ?? null,
                            },
                        };
                    }
                    if (mark.type === 'textStyle' && styles) {
                        const attrs = mark.attrs as
                            Record<string, unknown> | undefined;
                        if (
                            Object.keys(mark).some(
                                (key) => key !== 'type' && key !== 'attrs',
                            ) ||
                            !attrs ||
                            typeof attrs !== 'object' ||
                            Array.isArray(attrs)
                        )
                            throw new Error('Unsupported text style.');
                        const set = Object.entries(attrs).filter(
                            ([, value]) =>
                                value !== null && value !== undefined,
                        );
                        if (
                            !set.length ||
                            set.some(
                                ([key, value]) =>
                                    !(key in STYLES) || !STYLES[key](value),
                            )
                        )
                            throw new Error(
                                'Unsupported or unsafe text style.',
                            );
                        return {
                            type: 'textStyle',
                            attrs: {
                                color: null,
                                fontFamily: null,
                                fontSize: null,
                                ...Object.fromEntries(set),
                            },
                        };
                    }
                    if (
                        !MARKS.includes(String(mark.type)) ||
                        Object.keys(mark).length !== 1
                    )
                        throw new Error('Unsupported rich-text mark.');
                    return {
                        type:
                            mark.type === 'inlineCode'
                                ? 'code'
                                : String(mark.type),
                    };
                });
            }
        } else if (type === 'image') {
            const attrs = node.attrs as Record<string, unknown> | undefined;
            if (
                !attrs ||
                typeof attrs !== 'object' ||
                Array.isArray(attrs) ||
                Object.keys(attrs).some(
                    (name) => name !== 'src' && name !== 'alt',
                ) ||
                (attrs.alt !== undefined &&
                    attrs.alt !== null &&
                    (typeof attrs.alt !== 'string' ||
                        attrs.alt.length > 500)) ||
                !isImageSource(attrs.src, imageSources)
            ) {
                throw new Error('Unsupported or unsafe image.');
            }
            result.attrs = {
                src: attrs.src,
                alt: (attrs.alt as string | null | undefined) ?? null,
            };
        } else if (type !== 'hardBreak') {
            if (node.content !== undefined && !Array.isArray(node.content))
                throw new Error('Invalid rich-text content.');
            const children = [...((node.content ?? []) as unknown[])];
            if (type === 'doc' && children.length === 0)
                children.push({ type: 'paragraph' });
            if (
                type !== 'paragraph' &&
                type !== 'heading' &&
                children.length === 0
            )
                throw new Error('Rich-text containers cannot be empty.');
            if (children.length)
                result.content = children.map((child) =>
                    visit(child, type, depth + 1),
                );
            if (
                type === 'listItem' &&
                result.content?.[0]?.type !== 'paragraph'
            )
                throw new Error('List items must start with a paragraph.');
        }
        if (type === 'paragraph' && node.attrs !== undefined) {
            const attrs = node.attrs as Record<string, unknown> | null;
            if (
                !attrs ||
                typeof attrs !== 'object' ||
                Array.isArray(attrs) ||
                Object.keys(attrs).some((name) => name !== 'textAlign') ||
                !ALIGNMENTS.includes(attrs.textAlign as string)
            )
                throw new Error('Unsupported rich-text attributes.');
            result.attrs = { textAlign: attrs.textAlign };
        }
        if (type === 'heading' || type === 'orderedList') {
            const attrs = node.attrs as Record<string, unknown> | undefined;
            const key = type === 'heading' ? 'level' : 'start';
            const names =
                type === 'heading' && aligns ? [key, 'textAlign'] : [key];
            if (
                attrs !== undefined &&
                (!attrs ||
                    typeof attrs !== 'object' ||
                    Array.isArray(attrs) ||
                    Object.keys(attrs).some((name) => !names.includes(name)))
            )
                throw new Error('Unsupported rich-text attributes.');
            const value =
                attrs && key in attrs
                    ? attrs[key]
                    : type === 'orderedList'
                      ? 1
                      : undefined;
            if (
                type === 'heading'
                    ? value !== 2 && value !== 3
                    : !Number.isSafeInteger(value) || Number(value) < 1
            )
                throw new Error('Invalid rich-text attributes.');
            if (
                attrs &&
                'textAlign' in attrs &&
                !ALIGNMENTS.includes(attrs.textAlign as string)
            )
                throw new Error('Invalid rich-text attributes.');
            result.attrs = {
                [key]: value,
                ...(attrs && 'textAlign' in attrs
                    ? { textAlign: attrs.textAlign }
                    : {}),
            };
        }
        return result;
    };
    return visit(document ?? emptyDocument(), '', 0);
}

/** The editor's JSON back to the stored subset: tiptap's own defaults dropped (an unset alignment or style), `code` renamed `inlineCode`, then validated as toEditorDocument does (it throws too). */
export function fromEditorDocument(
    document: JSONContent,
    privateLinks?: RegExp | null,
    allow: readonly RichTextAllow[] = [],
    fontFamilies?: readonly string[] | null,
    imageSources?: RegExp | null,
): RichDocument {
    const set = (attrs: Record<string, unknown> | undefined) =>
        Object.fromEntries(
            Object.entries(attrs ?? {}).filter(
                ([, value]) => value !== null && value !== undefined,
            ),
        );
    const visit = (node: JSONContent): Record<string, unknown> => {
        const result: Record<string, unknown> = { type: node.type };
        if (node.text !== undefined) result.text = node.text;
        if (node.content?.length) result.content = node.content.map(visit);
        const align =
            typeof node.attrs?.textAlign === 'string'
                ? { textAlign: node.attrs.textAlign }
                : {};
        if (node.type === 'heading')
            result.attrs = { level: node.attrs?.level, ...align };
        if (node.type === 'paragraph' && 'textAlign' in align)
            result.attrs = align;
        if (node.type === 'orderedList')
            result.attrs = { start: node.attrs?.start ?? 1 };
        if (node.type === 'image')
            result.attrs = {
                src: node.attrs?.src,
                alt: node.attrs?.alt ?? null,
            };
        if (node.marks?.length) {
            result.marks = node.marks
                .map((mark) =>
                    mark.type === 'link'
                        ? {
                              type: 'link',
                              attrs: {
                                  href: mark.attrs?.href,
                                  title: mark.attrs?.title ?? null,
                              },
                          }
                        : mark.type === 'textStyle'
                          ? { type: 'textStyle', attrs: set(mark.attrs) }
                          : {
                                type:
                                    mark.type === 'code'
                                        ? 'inlineCode'
                                        : mark.type,
                            },
                )
                // A style whose every value was cleared is no mark at all.
                .filter(
                    (mark) =>
                        mark.type !== 'textStyle' ||
                        Object.keys(mark.attrs as object).length > 0,
                );
            if (!(result.marks as unknown[]).length) delete result.marks;
        }
        return result;
    };
    const result = visit(document) as unknown as RichDocument;
    toEditorDocument(result, privateLinks, allow, fontFamilies, imageSources);
    return result;
}

/**
 * A paste or drop that carries files: the intent to emit (null while the field is locked, so nothing happens) and whether the editor must stop there. Files are
 * always handled, so the browser never inserts a file's data URL or a pasted image into the document.
 */
export function fileIntent(
    source: FileIntent['source'],
    files: ArrayLike<File> | null | undefined,
    position: number,
    locked: boolean,
): { handled: boolean; intent: FileIntent | null } {
    if (!files?.length) return { handled: false, intent: null };
    return {
        handled: true,
        intent: locked ? null : { source, files: Array.from(files), position },
    };
}

/** A paste or drop split for an editor that uploads its own images: the image files it inserts itself, and the rest, still the host's file-intent. */
export function splitImageFiles(files: readonly File[]): {
    images: File[];
    rest: File[];
} {
    const images = files.filter((file) =>
        /^image\/(png|jpe?g|gif|webp|avif)$/i.test(file.type),
    );
    return { images, rest: files.filter((file) => !images.includes(file)) };
}
