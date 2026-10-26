// Space an aside may use while sticky: the viewport below the shell header (`top`) minus a bottom margin, both in rem.
export const STICKY_TOP_REM = 7.5;
export const STICKY_BOTTOM_REM = 5.5;

// An aside is sticky only while all of it fits the viewport; taller, it stays in the flow and scrolls with the page (one scrollbar per page).
export function fitsViewport(contentHeight: number, viewportHeight: number, rem: number, topRem = STICKY_TOP_REM, bottomRem = STICKY_BOTTOM_REM): boolean {
    if (![contentHeight, viewportHeight, rem].every(Number.isFinite) || rem <= 0) return false;
    return contentHeight <= viewportHeight - (topRem + bottomRem) * rem;
}
