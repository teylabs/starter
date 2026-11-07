// The EmojiTray's pure parts: its default reactions, and the throttle that keeps a tray from emitting faster than the server accepts. No DOM.
export type EmojiReaction = { emoji: string; label: string };

export const DEFAULT_REACTIONS: EmojiReaction[] = [
    { emoji: '👍', label: 'Thumbs up' },
    { emoji: '❤️', label: 'Love' },
    { emoji: '😂', label: 'Laugh' },
    { emoji: '😮', label: 'Wow' },
    { emoji: '👏', label: 'Applause' },
    { emoji: '🎉', label: 'Celebrate' },
];

/** A gate that lets one call through, then none until `ms` have passed since the last one it let through. `now` is injectable for tests. */
export function throttleGate(
    ms: number,
    now: () => number = () => Date.now(),
): () => boolean {
    let last = Number.NEGATIVE_INFINITY;
    return () => {
        const at = now();
        if (at - last < ms) return false;
        last = at;
        return true;
    };
}

/** The index the arrow, Home and End keys move to in a row of `count` buttons, wrapping; null for any other key. */
export function rovingIndex(
    key: string,
    current: number,
    count: number,
): number | null {
    if (count <= 0) return null;
    switch (key) {
        case 'ArrowRight':
        case 'ArrowDown':
            return (current + 1) % count;
        case 'ArrowLeft':
        case 'ArrowUp':
            return (current - 1 + count) % count;
        case 'Home':
            return 0;
        case 'End':
            return count - 1;
        default:
            return null;
    }
}
