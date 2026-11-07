/*
 * SwatchPicker's colour maths, plain TypeScript (tests/js/swatch-picker.test.mjs runs it without Vue). Colours are #RRGGBB, upper case.
 */

/** "#abc123", "abc123" or " #ABC123 " → "#ABC123"; anything else → null. Three-digit shorthand is not a colour here. */
export function normaliseHex(raw: string): string | null {
    const trimmed = raw.trim();
    const hex = (
        trimmed.startsWith('#') ? trimmed : `#${trimmed}`
    ).toUpperCase();
    return /^#[0-9A-F]{6}$/.test(hex) ? hex : null;
}

/** WCAG 2 relative luminance of a #RRGGBB colour, 0 (black) to 1 (white). */
export function luminance(hex: string): number {
    const channel = (offset: number): number => {
        const c = parseInt(hex.slice(offset, offset + 2), 16) / 255;
        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    };
    return 0.2126 * channel(1) + 0.7152 * channel(3) + 0.0722 * channel(5);
}

/** WCAG 2 contrast ratio between two #RRGGBB colours, 1 to 21. */
export function contrastRatio(a: string, b: string): number {
    const [high, low] = [luminance(a), luminance(b)].sort((x, y) => y - x);
    return (high + 0.05) / (low + 0.05);
}

/** The check mark's colour on a swatch: black on a light swatch, white on a dark one, whichever contrasts more. */
export function markColour(hex: string): '#000000' | '#FFFFFF' {
    return contrastRatio(hex, '#000000') >= contrastRatio(hex, '#FFFFFF')
        ? '#000000'
        : '#FFFFFF';
}

/** The swatches in order, each once: the suggested ones first, then the palette without the suggested ones. */
export function swatchList(
    suggested: readonly string[],
    palette: readonly string[],
): { hex: string; suggested: boolean }[] {
    const seen = new Set<string>();
    const out: { hex: string; suggested: boolean }[] = [];
    for (const [list, isSuggested] of [
        [suggested, true],
        [palette, false],
    ] as const) {
        for (const raw of list) {
            const hex = normaliseHex(raw);
            if (hex !== null && !seen.has(hex)) {
                seen.add(hex);
                out.push({ hex, suggested: isSuggested });
            }
        }
    }
    return out;
}
